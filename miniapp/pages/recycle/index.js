/**
 * pages/recycle/index.js —— 上门回收预约
 *  1) 选择预估重量段
 *  2) 选择上门日期 + 时间
 *  3) 可选上传物品照片（wx.chooseMedia → 逐个 wx.uploadFile 到 /api/upload）
 *  4) 提交 → POST /api/recycle/reserve → 成功提示后返回
 */
const config = require('../../config.js');
const { request } = require('../../utils/request.js');

const WEIGHT_OPTIONS = ['3~20kg', '>20kg'];

Page({
  data: {
    estimatedWeight: '',
    appointmentDate: '',
    appointmentTime: '',
    images: [],         // 已上传成功的线上 URL 数组
    remark: '',
    uploading: false,
    submitting: false,
  },

  // ---------- 重量选择 ----------
  onSelectWeight(e) {
    const value = e.currentTarget.dataset.value;
    if (WEIGHT_OPTIONS.indexOf(value) === -1) return;
    this.setData({ estimatedWeight: value });
  },

  // ---------- 日期 / 时间 ----------
  onDateChange(e) {
    this.setData({ appointmentDate: e.detail.value });
  },
  onTimeChange(e) {
    this.setData({ appointmentTime: e.detail.value });
  },

  // ---------- 备注 ----------
  onRemarkInput(e) {
    this.setData({ remark: e.detail.value });
  },

  // ---------- 图片选择与上传 ----------
  onChooseImages() {
    if (this.data.uploading) {
      wx.showToast({ title: '图片上传中，请稍候', icon: 'none' });
      return;
    }
    const remain = 9 - this.data.images.length;
    if (remain <= 0) {
      wx.showToast({ title: '最多上传 9 张', icon: 'none' });
      return;
    }

    wx.chooseMedia({
      count: remain,
      mediaType: ['image'],
      sourceType: ['album', 'camera'],
      sizeType: ['compressed'],
      success: (res) => {
        const files = (res.tempFiles || []).map((f) => f.tempFilePath);
        if (files.length === 0) return;
        this._uploadImages(files);
      },
      fail: (err) => {
        if (err && /cancel/i.test(err.errMsg || '')) return;
        wx.showToast({ title: '选择图片失败', icon: 'none' });
      },
    });
  },

  /**
   * 逐个上传图片到 /api/upload，成功的 URL 追加到 images 数组
   */
  _uploadImages(files) {
    this.setData({ uploading: true });
    const token = wx.getStorageSync(config.TOKEN_KEY);
    const uploadUrl = config.BASE_URL.replace(/\/$/, '') + '/api/upload';

    const uploadOne = (filePath) => new Promise((resolve) => {
      wx.uploadFile({
        url: uploadUrl,
        filePath,
        name: 'file',
        header: {
          'Authorization': 'Bearer ' + (token || ''),
          'Accept': 'application/json',
        },
        success: (res) => {
          try {
            const body = JSON.parse(res.data);
            if (body.code === 200 && body.data && body.data.url) {
              resolve(body.data.url);
            } else {
              resolve(null);
            }
          } catch (e) {
            resolve(null);
          }
        },
        fail: () => resolve(null),
      });
    });

    wx.showLoading({ title: '上传中…', mask: true });
    Promise.all(files.map(uploadOne))
      .then((urls) => {
        const valid = urls.filter((u) => !!u);
        if (valid.length < files.length) {
          wx.showToast({
            title: `${files.length - valid.length} 张上传失败`,
            icon: 'none',
          });
        }
        if (valid.length > 0) {
          this.setData({ images: this.data.images.concat(valid) });
        }
      })
      .finally(() => {
        wx.hideLoading();
        this.setData({ uploading: false });
      });
  },

  // ---------- 删除已选图片 ----------
  onDeleteImage(e) {
    const index = e.currentTarget.dataset.index;
    const images = this.data.images.slice();
    images.splice(index, 1);
    this.setData({ images });
  },

  // ---------- 提交 ----------
  onSubmit() {
    if (this.data.submitting || this.data.uploading) return;

    // 校验：预估重量
    if (!this.data.estimatedWeight) {
      wx.showToast({ title: '请选择预估重量', icon: 'none' });
      return;
    }
    // 校验：上门时间
    if (!this.data.appointmentDate || !this.data.appointmentTime) {
      wx.showToast({ title: '请选择上门时间', icon: 'none' });
      return;
    }

    // 拼接 datetime 字符串：YYYY-MM-DD HH:mm:ss
    const appointmentTime = `${this.data.appointmentDate} ${this.data.appointmentTime}:00`;

    this.setData({ submitting: true });

    const payload = {
      estimated_weight: this.data.estimatedWeight,
      appointment_time: appointmentTime,
      images: this.data.images,
      remark: (this.data.remark || '').trim(),
    };

    request.post('/api/recycle/reserve', payload, { auth: true })
      .then(() => {
        wx.showToast({
          title: '预约成功，工作人员将尽快联系您',
          icon: 'success',
          duration: 2000,
        });
        setTimeout(() => {
          wx.navigateBack({ delta: 1 });
        }, 1000);
      })
      .catch((err) => {
        console.error('[recycle] submit fail:', err && err.message);
      })
      .finally(() => {
        this.setData({ submitting: false });
      });
  },
});
