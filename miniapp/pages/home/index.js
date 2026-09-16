/**
 * pages/home/index.js —— 首页大盘（按角色分流）
 *   - 居民：轮播图 + 居民消费金 / 出示核销码 宫格
 *   - 商户：全屏"扫码核销"大按钮 → 扫码 → /api/qrcode/resolve → 跳扣款页
 */
const config = require('../../config.js');
const { request } = require('../../utils/request.js');
const app = getApp();

Page({
  data: {
    slides: [],
    activities: [],
    role: 'resident',
    userInfo: {},
    todayCount: 0,
  },

  onLoad() {
    this._syncRole();
    this.loadSlides();
    this.loadActivities();
  },

  onShow() {
    this._syncRole();
    // 居民端每次回首页重新拉轮播 + 活动
    if (this.data.role !== 'merchant') {
      this.loadSlides();
      this.loadActivities();
    }
  },

  onPullDownRefresh() {
    this.loadSlides(() => {});
    this.loadActivities(() => wx.stopPullDownRefresh());
  },

  _syncRole() {
    const g = app.globalData;
    const role = wx.getStorageSync(config.ROLE_KEY) || g.role || 'resident';
    this.setData({
      role,
      userInfo: g.userInfo || {},
    });
  },

  loadSlides(done) {
    // 轮播图是公开接口：auth:false
    request.get('/api/slides', {}, { auth: false })
      .then((res) => {
        this.setData({
          slides: Array.isArray(res.data) ? res.data : [],
        });
      })
      .catch((err) => {
        console.warn('[home] load slides failed:', err && err.message);
        this.setData({ slides: [] });
      })
      .finally(() => {
        done && done();
      });
  },

  /** 加载近期活动列表（公开接口） */
  loadActivities(done) {
    request.get('/api/activities', {}, { auth: false })
      .then((res) => {
        const data = res.data || {};
        this.setData({
          activities: Array.isArray(data.items) ? data.items : [],
        });
      })
      .catch((err) => {
        console.warn('[home] load activities failed:', err && err.message);
        this.setData({ activities: [] });
      })
      .finally(() => {
        done && done();
      });
  },

  /** 点击活动卡片 → 跳转详情页 */
  onActivityTap(e) {
    const id = e.currentTarget.dataset.id;
    if (!id) return;
    wx.navigateTo({
      url: `/pages/activity/detail?id=${id}`,
    });
  },

  // 轮播图点击（预留）
  onSlideTap(e) {
    const item = e.currentTarget.dataset.item;
    if (!item) return;
    wx.showToast({
      title: item.title || `轮播 #${item.id}`,
      icon: 'none',
    });
  },

  // 宫格 → 居民消费金
  onPointsTap() {
    wx.navigateTo({
      url: '/pages/points/index',
      fail: (err) => {
        console.warn('[home] nav points fail:', err && err.errMsg);
        wx.showToast({ title: '功能开发中', icon: 'none' });
      },
    });
  },

  // 宫格 → 出示核销码（居民端）
  onQrCodeTap() {
    wx.navigateTo({
      url: '/pages/qrcode/index',
      fail: (err) => {
        console.warn('[home] nav qrcode fail:', err && err.errMsg);
        wx.showToast({ title: '打开核销码失败', icon: 'none' });
      },
    });
  },

  // 商户 → 扫码核销
  onMerchantScan() {
    wx.scanCode({
      onlyFromCamera: true,
      scanType: ['qrCode'],
      success: (res) => {
        const token = res.result;
        if (!token) {
          wx.showToast({ title: '未识别到核销码', icon: 'none' });
          return;
        }
        this._resolveQr(token);
      },
      fail: (err) => {
        if (err && /cancel/i.test(err.errMsg || '')) return;
        wx.showToast({ title: '扫码失败：' + (err.errMsg || 'unknown'), icon: 'none' });
      },
    });
  },

  // 商户：用扫到的 token 调后端解析被核销用户
  _resolveQr(token) {
    wx.showLoading({ title: '解析中…', mask: true });
    request.post('/api/qrcode/resolve', { token })
      .then((res) => {
        const u = res.data || {};
        if (!u.id) throw new Error('解析失败：未返回用户信息');

        // 跳到扣款页，通过 URL 参数传用户信息
        const params = [
          'id=' + u.id,
          'name=' + encodeURIComponent(u.name || '微信用户'),
          'avatar=' + encodeURIComponent(u.avatar || ''),
          'points=' + u.points,
        ].join('&');
        wx.navigateTo({
          url: '/pages/merchant/deduct?' + params,
        });
      })
      .catch((err) => {
        // 过期/无效由后端返回 message，request 已 toast
        console.warn('[home] resolve fail:', err);
      })
      .finally(() => wx.hideLoading());
  },

  // 居民 → 上门回收预约
  onRecycleTap() {
    wx.navigateTo({
      url: '/pages/recycle/index',
      fail: (err) => {
        console.warn('[home] nav recycle fail:', err && err.errMsg);
        wx.showToast({ title: '功能开发中', icon: 'none' });
      },
    });
  },

  // 居民 → 志愿活动扫码签到 / 签退
  onScanActivity() {
    wx.scanCode({
      onlyFromCamera: true,
      scanType: ['qrCode'],
      success: (res) => {
        const qrcodeToken = res.result;
        if (!qrcodeToken) {
          wx.showToast({ title: '未识别到活动二维码', icon: 'none' });
          return;
        }
        this._submitActivityScan(qrcodeToken);
      },
      fail: (err) => {
        if (err && /cancel/i.test(err.errMsg || '')) return;
        wx.showToast({ title: '扫码失败：' + (err.errMsg || 'unknown'), icon: 'none' });
      },
    });
  },

  // 调用后端扫码打卡接口，根据 type 弹出签到/签退提示
  _submitActivityScan(qrcodeToken) {
    wx.showLoading({ title: '处理中…', mask: true });
    request.post('/api/activity/scan', { qrcode_token: qrcodeToken })
      .then((res) => {
        const data = res.data || {};
        if (data.type === 'check_in') {
          wx.showModal({
            title: '签到成功',
            content: '您已成功签到，祝您服务顺利！',
            showCancel: false,
            confirmText: '好的',
          });
        } else if (data.type === 'check_out') {
          const hours = Number(data.hours || 0).toFixed(2);
          const points = Number(data.points || 0).toFixed(2);

          // 签退成功 → 同步更新本地消费金缓存（首页数字即时刷新）
          const currentPoints = Number(app.globalData.points || 0);
          const newPoints = currentPoints + Number(data.points || 0);
          app.setPoints(newPoints);

          wx.showModal({
            title: '签退成功',
            content: `本次服务 ${hours} 小时，获得 ${points} 消费金，已入账。感谢您的付出！`,
            showCancel: false,
            confirmText: '好的',
          });
        }
      })
      .catch((err) => {
        // 后端返回的业务错误（如活动停用、时长过短）已由 request 统一 toast
        console.warn('[home] activity scan fail:', err);
      })
      .finally(() => wx.hideLoading());
  },
});
