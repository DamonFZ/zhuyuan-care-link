/**
 * pages/user/edit.js —— 编辑个人信息
 * 合规（微信隐私新规）：
 *   · 头像：<button open-type="chooseAvatar" bind:chooseavatar="onChooseAvatar">
 *           用户选到 → 临时文件路径 → wx.uploadFile 到 POST /api/upload → 得到线上 URL
 *   · 昵称：<input type="nickname" />
 *   · 保存：POST /api/user/profile { name, avatar } → 成功后写回全局缓存并返回上页
 */
const config = require('../../config.js');
const { request } = require('../../utils/request.js');
const app = getApp();

Page({
  data: {
    avatarUrl: '',        // 展示用：可能是临时路径（待上传）或已上传的线上 URL
    avatarServerUrl: '',  // 已上传成功的线上 URL（最终保存字段）
    nickname: '',
    uploading: false,
    saving: false,
  },

  onLoad() {
    // 回显现有信息
    const g = app.globalData;
    const u = g.userInfo || {};
    this.setData({
      avatarUrl: u.avatar || '',
      avatarServerUrl: u.avatar || '',
      nickname: u.name || '',
    });
  },

  // =========================================================
  // 1) 头像选择 + 上传（微信合规 chooseAvatar）
  // =========================================================
  onChooseAvatar(e) {
    // 不同基础库可能返回 avatarUrl 或 detail.avatarUrl
    const tempUrl = (e.detail && e.detail.avatarUrl) || e.avatarUrl;
    if (!tempUrl) {
      wx.showToast({ title: '未获取到头像', icon: 'none' });
      return;
    }

    // 先渲染到页面，同时异步上传到后端
    this.setData({ avatarUrl: tempUrl, uploading: true });
    this._uploadAvatar(tempUrl);
  },

  _uploadAvatar(tempFilePath) {
    const token = wx.getStorageSync(config.TOKEN_KEY);
    wx.showLoading({ title: '头像上传中', mask: true });

    wx.uploadFile({
      url: config.BASE_URL.replace(/\/$/, '') + '/api/upload',
      filePath: tempFilePath,
      name: 'file',                         // 与后端 UploadController 接收字段 'file' 对应
      header: {
        'Authorization': 'Bearer ' + (token || ''),
        'Accept': 'application/json',
      },
      formData: {
        source: 'wechat-miniapp-avatar',
      },
      success: (res) => {
        try {
          const body = JSON.parse(res.data);
          if (body.code === 200 && body.data && body.data.url) {
            const url = body.data.url;
            this.setData({
              avatarServerUrl: url,
              // 上传后仍展示 temp（base64/temp 都是本地），但保存用 avatarServerUrl
            });
            wx.showToast({ title: '头像上传成功', icon: 'success' });
            return;
          }
          throw new Error(body.message || '上传失败');
        } catch (err) {
          console.error('[edit] uploadFile parse err:', err, 'raw:', res.data);
          wx.showToast({
            title: (err && err.message) || '上传失败',
            icon: 'none',
          });
        }
      },
      fail: (err) => {
        wx.showToast({
          title: '网络错误：' + (err.errMsg || 'unknown'),
          icon: 'none',
        });
      },
      complete: () => {
        wx.hideLoading();
        this.setData({ uploading: false });
      },
    });
  },

  // =========================================================
  // 2) 昵称输入（微信合规 type="nickname"）
  // =========================================================
  onNicknameInput(e) {
    this.setData({ nickname: (e.detail && e.detail.value) || '' });
  },
  onNicknameBlur(e) {
    // 微信基础库会在 blur 时把 type="nickname" 填入真实昵称（部分版本触发）
    const v = (e.detail && e.detail.value) || this.data.nickname;
    this.setData({ nickname: v });
  },

  // =========================================================
  // 3) 保存
  // =========================================================
  onSave() {
    if (this.data.uploading) {
      wx.showToast({ title: '头像上传中，请稍候', icon: 'none' });
      return;
    }
    const name = (this.data.nickname || '').trim();
    if (!name) {
      wx.showToast({ title: '昵称不能为空', icon: 'none' });
      return;
    }
    if (name.length > 50) {
      wx.showToast({ title: '昵称最长 50 字符', icon: 'none' });
      return;
    }

    this.setData({ saving: true });
    const payload = {
      name,
    };
    // 仅在已上传成功（或已存在）时提交 avatar
    if (this.data.avatarServerUrl) {
      payload.avatar = this.data.avatarServerUrl;
    }

    request.post('/api/user/profile', payload)
      .then((res) => {
        wx.showToast({ title: '保存成功', icon: 'success' });

        // ---- 更新全局缓存（避免下次 onShow 前读到旧数据） ----
        const userInfo = Object.assign({}, app.globalData.userInfo || {}, {
          name: payload.name,
        });
        if (payload.avatar) userInfo.avatar = payload.avatar;

        // 写 storage
        wx.setStorageSync(config.USER_KEY, userInfo);
        app.globalData.userInfo = userInfo;
        if (payload.avatar) {
          // 头像本身也在 USER_KEY 里，但也同步回前端读 avatar 的逻辑
        }

        setTimeout(() => {
          wx.navigateBack({ delta: 1 });
        }, 600);
      })
      .catch((err) => {
        wx.showToast({
          title: (err && err.message) || '保存失败',
          icon: 'none',
        });
      })
      .finally(() => {
        this.setData({ saving: false });
      });
  },
});
