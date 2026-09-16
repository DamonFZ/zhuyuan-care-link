/**
 * pages/activity/detail.js —— 活动详情与报名
 *
 * 按钮状态机：
 *   - is_ended        → "已结束"，置灰禁用
 *   - has_registered  → "已报名"，置灰禁用
 *   - 否则            → "立即报名"，主色调可点击
 *
 * 报名成功后刷新详情，按钮自动变为"已报名"。
 */
const { request } = require('../../utils/request.js');

Page({
  data: {
    id: null,
    activity: {},
    loading: true,
    loadError: '',
    registering: false,
    btnText: '立即报名',
    btnDisabled: false,
  },

  onLoad(options) {
    const id = options && options.id;
    if (!id) {
      this.setData({ loading: false, loadError: '缺少活动 ID' });
      return;
    }
    this.setData({ id });
    this.loadDetail();
  },

  /** 拉取活动详情 */
  loadDetail() {
    this.setData({ loading: true, loadError: '' });
    request.get(`/api/activities/${this.data.id}`, {}, { auth: true })
      .then((res) => {
        const activity = res.data || {};
        this.setData({
          activity,
          loading: false,
        });
        this._updateBtnState(activity);
      })
      .catch((err) => {
        console.error('[activity detail] load fail:', err && err.message);
        this.setData({
          loading: false,
          loadError: (err && err.message) || '加载失败，请重试',
        });
      });
  },

  /** 根据活动状态更新底部按钮 */
  _updateBtnState(activity) {
    if (activity.is_ended) {
      this.setData({ btnText: '已结束', btnDisabled: true });
    } else if (activity.has_registered) {
      this.setData({ btnText: '已报名', btnDisabled: true });
    } else {
      this.setData({ btnText: '立即报名', btnDisabled: false });
    }
  },

  /** 点击报名 */
  onRegister() {
    if (this.data.btnDisabled || this.data.registering) return;

    this.setData({ registering: true });
    request.post(`/api/activities/${this.data.id}/register`, {}, { auth: true })
      .then((res) => {
        wx.showToast({
          title: (res && res.message) || '报名成功',
          icon: 'success',
        });
        // 刷新详情，按钮变为"已报名"
        this.loadDetail();
      })
      .catch((err) => {
        const msg = (err && err.message) || '报名失败，请稍后重试';
        wx.showToast({ title: msg, icon: 'none' });
      })
      .finally(() => {
        this.setData({ registering: false });
      });
  },
});
