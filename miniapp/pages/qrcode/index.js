/**
 * pages/qrcode/index.js —— 动态核销码
 *
 * 流程：
 *   1) onLoad：记录原屏幕亮度 → 调到最高 (value:1)；拉取首个 token
 *   2) fetchQrToken：GET /api/qrcode/generate → 后端已生成 SVG base64
 *      直接绑定 qr_image 到 <image>，前端零 Canvas 绘制
 *   3) setInterval 每 60s 自动刷新
 *   4) onUnload：清定时器 + 恢复屏幕亮度
 */
const { request } = require('../../utils/request.js');
const app = getApp();

const REFRESH_INTERVAL = 60; // 秒

Page({
  data: {
    token: '',
    qr_image: '',       // 后端返回的 base64 SVG data URL
    userInfo: {},
    points: 0,
    countdown: REFRESH_INTERVAL,
  },

  _originalBrightness: 1,
  _timer: null,
  _countdownTimer: null,

  onLoad() {
    const g = app.globalData;
    const userInfo = g.userInfo || {};
    this.setData({
      userInfo,
      points: typeof g.points === 'number' ? g.points : 0,
    });

    // 1) 记录并调高屏幕亮度（保证扫码枪/手机可清晰识别）
    try {
      wx.getScreenBrightness({
        success: (res) => {
          this._originalBrightness = typeof res.value === 'number' ? res.value : 1;
          wx.setScreenBrightness({ value: 1 });
        },
        fail: () => {
          this._originalBrightness = 1;
          wx.setScreenBrightness({ value: 1 });
        },
      });
    } catch (e) {
      this._originalBrightness = 1;
      wx.setScreenBrightness({ value: 1 });
    }

    // 2) 拉第一个 token（含 base64 二维码）
    this.fetchQrToken();

    // 3) 每 60s 自动刷新
    this._timer = setInterval(() => this.fetchQrToken(), REFRESH_INTERVAL * 1000);

    // 4) 倒计时显示
    this.setData({ countdown: REFRESH_INTERVAL });
    this._countdownTimer = setInterval(() => {
      let c = this.data.countdown - 1;
      if (c <= 0) c = REFRESH_INTERVAL;
      this.setData({ countdown: c });
    }, 1000);
  },

  onUnload() {
    if (this._timer) {
      clearInterval(this._timer);
      this._timer = null;
    }
    if (this._countdownTimer) {
      clearInterval(this._countdownTimer);
      this._countdownTimer = null;
    }
    try {
      wx.setScreenBrightness({ value: this._originalBrightness });
    } catch (e) { /* ignore */ }
  },

  onHide() {
    try {
      wx.setScreenBrightness({ value: this._originalBrightness });
    } catch (e) { /* ignore */ }
  },

  onShow() {
    // 回到页面重新拉（避免后台期间 token 过期回来还是旧的）
    this.fetchQrToken();
    this.setData({ countdown: REFRESH_INTERVAL });
  },

  /**
   * 拉取动态核销 token + 后端生成的 base64 二维码
   */
  fetchQrToken() {
    request.get('/api/qrcode/generate', {}, { auth: true })
      .then((res) => {
        const data = res.data || {};
        if (!data.token || !data.qr_image) {
          throw new Error('未获取到核销码');
        }

        this.setData({
          token: data.token,
          qr_image: data.qr_image,   // 直接绑定后端返回的 Base64
          countdown: data.ttl || REFRESH_INTERVAL,
        });
      })
      .catch((err) => {
        console.error('[qrcode] fetch token fail:', err && err.message);
        wx.showToast({
          title: (err && err.message) || '核销码生成失败',
          icon: 'none',
        });
      });
  },
});
