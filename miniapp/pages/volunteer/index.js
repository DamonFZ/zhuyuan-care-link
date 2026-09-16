/**
 * pages/volunteer/index.js —— 志愿服务打卡明细
 * GET /api/user/volunteer-attendances 分页拉取，onReachBottom 上拉加载更多
 */
const { request } = require('../../utils/request.js');

Page({
  data: {
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
    loadingMore: false,
  },

  onLoad() {
    this.fetchList(true);
  },

  /**
   * 拉取打卡记录
   * @param {boolean} reset 是否重置到第 1 页
   */
  fetchList(reset = false) {
    if (this.data.loading || this.data.loadingMore) return;
    if (!reset && !this.data.hasMore) return;

    const page = reset ? 1 : this.data.page;
    if (reset) {
      this.setData({ loading: true, page: 1, hasMore: true });
    } else {
      this.setData({ loadingMore: true });
    }

    return request.get('/api/user/volunteer-attendances', { page }, { auth: true })
      .then((res) => {
        const data = res.data || {};
        const items = (data.items || []).map((att) => ({
          ...att,
          // 服务时长保留两位小数
          service_hours: att.service_hours !== null ? Number(att.service_hours).toFixed(2) : null,
        }));

        const newList = reset ? items : this.data.list.concat(items);
        this.setData({
          list: newList,
          page: data.current_page || page,
          hasMore: !!data.has_more_pages,
          loading: false,
          loadingMore: false,
        });
      })
      .catch((err) => {
        console.error('[volunteer] fetch fail:', err && err.message);
        this.setData({ loading: false, loadingMore: false });
      });
  },

  /** 上拉加载更多 */
  onReachBottom() {
    if (this.data.hasMore && !this.data.loadingMore) {
      this.setData({ page: this.data.page + 1 });
      this.fetchList(false);
    }
  },

  /** 下拉刷新：重置到第 1 页并清空旧数据 */
  onPullDownRefresh() {
    this.setData({ list: [], page: 1, hasMore: true });
    this.fetchList(true).finally(() => {
      wx.stopPullDownRefresh();
    });
  },
});
