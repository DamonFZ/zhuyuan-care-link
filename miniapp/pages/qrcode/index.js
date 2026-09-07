/**
 * pages/qrcode/index.js —— 动态核销码
 *
 * 流程：
 *   1) onLoad：记录原屏幕亮度 → 调到最高 (value:1)；拉取首个 token
 *   2) fetchQrToken：GET /api/qrcode/generate → 拿到 token → 画到 canvas
 *   3) setInterval 每 60s 自动刷新 token + 重绘
 *   4) onUnload：清定时器 + 恢复屏幕亮度
 *
 * 二维码绘制：自实现 utils/qrcode.js 生成模块矩阵 → ctx.fillRect 逐块绘制
 */
const { request } = require('../../utils/request.js');
const QRCode = require('../../utils/qrcode.js');
const app = getApp();

const REFRESH_INTERVAL = 60; // 秒

Page({
  data: {
    token: '',
    userInfo: {},
    points: 0,
    qrCanvasSize: 240, // px（canvas 物理像素）
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

    // 2) 拉第一个 token
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
    // 清定时器
    if (this._timer) {
      clearInterval(this._timer);
      this._timer = null;
    }
    if (this._countdownTimer) {
      clearInterval(this._countdownTimer);
      this._countdownTimer = null;
    }
    // 恢复屏幕亮度
    try {
      wx.setScreenBrightness({ value: this._originalBrightness });
    } catch (e) { /* ignore */ }
  },

  onHide() {
    // 切后台也恢复亮度，避免影响用户其他操作
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
   * 拉取动态核销 token 并绘制二维码
   */
  fetchQrToken() {
    request.get('/api/qrcode/generate', {}, { auth: true })
      .then((res) => {
        const token = res.data && res.data.token;
        if (!token) throw new Error('未获取到核销码');

        this.setData({ token, countdown: REFRESH_INTERVAL });
        this.drawQr(token);
      })
      .catch((err) => {
        console.error('[qrcode] fetch token fail:', err && err.message);
        wx.showToast({
          title: (err && err.message) || '核销码生成失败',
          icon: 'none',
        });
      });
  },

  /**
   * 将 token 绘制为二维码到 canvas
   */
  drawQr(text) {
    try {
      const qr = QRCode.encode(text);
      const size = qr.size;
      // canvas 物理像素：用模块数 * 每模块像素，取整保证清晰
      const px = 8;
      const canvasPx = size * px;

      this.setData({ qrCanvasSize: canvasPx });

      const ctx = wx.createCanvasContext('qrcode', this);
      // 白底
      ctx.setFillStyle('#ffffff');
      ctx.fillRect(0, 0, canvasPx, canvasPx);
      // 黑模块
      ctx.setFillStyle('#1f2937');
      for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
          if (qr.modules[y][x]) {
            ctx.fillRect(x * px, y * px, px, px);
          }
        }
      }
      ctx.draw(false, () => {
        // 绘制完成回调（可选）
      });
    } catch (e) {
      console.error('[qrcode] draw fail:', e);
      wx.showToast({ title: '二维码绘制失败', icon: 'none' });
    }
  },
});
