/**
 * pages/home/index.js —— 首页大盘
 *   - onLoad / onPullDownRefresh: 拉 GET /api/slides 渲染轮播
 *   - 宫格：居民积分 → /pages/points/index；活动打卡 → wx.scanCode
 */
const { request } = require('../../utils/request.js');
const app = getApp();

Page({
  data: {
    slides: [],
  },

  onLoad() {
    this.loadSlides();
  },

  onShow() {
    // 每次回到首页都重新拉（后台可能新增/调整了轮播图排序）
    this.loadSlides();
  },

  onPullDownRefresh() {
    this.loadSlides(() => wx.stopPullDownRefresh());
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

  // 轮播图点击（预留：未来可根据 slide id 跳 H5 或 小程序页面）
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

  // 宫格 → 活动扫码打卡
  onScanTap() {
    wx.scanCode({
      onlyFromCamera: false,
      scanType: ['qrCode', 'barCode'],
      success: (res) => {
        wx.showToast({
          title: '扫码成功',
          icon: 'success',
        });
        console.log('[home] scan result:', res);
        // 临时：toast 打印扫码结果
        setTimeout(() => {
          wx.showModal({
            title: '扫码结果',
            content: `类型: ${res.scanType}\n内容: ${res.result}`,
            showCancel: false,
            confirmColor: '#7dd61a',
          });
        }, 600);
      },
      fail: (err) => {
        if (err && /cancel/i.test(err.errMsg || '')) return;
        wx.showToast({ title: '扫码失败：' + (err.errMsg || 'unknown'), icon: 'none' });
      },
    });
  },
});
