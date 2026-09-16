/**
 * pages/points/index.js —— 消费金流水
 * GET /api/user/point-transactions 分页拉取，onReachBottom 上拉加载更多
 */
const { request } = require('../../utils/request.js');

const PAGE_SIZE = 15;

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
   * 拉取流水
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

    return request.get('/api/user/point-transactions', { page }, { auth: true })
      .then((res) => {
        const data = res.data || {};
        const items = (data.items || []).map((tx) => ({
          ...tx,
          // 金额保留两位小数展示
          amount_display: Number(tx.amount).toFixed(2),
          new_points: Number(tx.new_points).toFixed(2),
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
        console.error('[points] fetch fail:', err && err.message);
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
