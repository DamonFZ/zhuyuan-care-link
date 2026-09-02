/**
 * pages/user/index.js —— 个人中心
 *  展示：头像 / 角色 / 消费金 / 等级 / 时长
 *  菜单：编辑个人信息（跳 pages/user/edit）/ 消费金流水 / 退出登录
 *  onShow：从 pages/user/edit 保存后，刷新本地缓存 → 页面重渲染
 */
Page({
  data: {
    loginReady: false,
    loginError: '',
    userInfo: null,
    roleText: '',
    points: '0.00',
    volunteerLevelName: '',
    avatarUrl: '',
  },

  onLoad() {
    this._waitForLogin();
  },

  /** 每次回到个人中心都刷新（可能在 edit 页面更新过） */
  onShow() {
    const app = getApp();
    if (app.globalData.loginReady) this._renderFromGlobal();
  },

  _waitForLogin() {
    const app = getApp();
    const check = () => {
      if (app.globalData.loginReady) {
        this._renderFromGlobal();
      } else if (app.globalData.loginError) {
        this.setData({
          loginReady: true,
          loginError: app.globalData.loginError,
        });
      } else {
        setTimeout(check, 200);
      }
    };
    check();
  },

  _renderFromGlobal() {
    const app = getApp();
    const u = app.globalData.userInfo || {};
    const roleMap = {
      resident: '社区居民',
      merchant: '核销商户',
      operator: '操作员',
    };
    this.setData({
      loginReady: true,
      loginError: '',
      userInfo: u,
      roleText: roleMap[app.globalData.role] || app.globalData.role,
      points: Number(app.globalData.points || 0).toFixed(2),
      volunteerLevelName: (app.globalData.volunteerLevel && app.globalData.volunteerLevel.name) || '未评级',
      avatarUrl: u.avatar || '',
    });
  },

  /** 📝 编辑个人信息 */
  onEditProfile() {
    wx.navigateTo({ url: '/pages/user/edit' });
  },

  /** 💳 消费金流水 → 暂跳 points 占位页 */
  onPointsTap() {
    wx.navigateTo({
      url: '/pages/points/index',
      fail: () => wx.showToast({ title: '开发中', icon: 'none' }),
    });
  },

  /** 🚪 退出登录 */
  onLogout() {
    wx.showModal({
      title: '退出登录？',
      content: '退出后下次打开小程序需重新授权微信登录。',
      confirmColor: '#ff7a33',
      success: (r) => {
        if (!r.confirm) return;
        const app = getApp();
        app.logout();
        this.setData({
          loginReady: false,
          loginError: '',
          avatarUrl: '',
        });
        // 重新拉起静默登录
        app.silentLogin().then(() => this._renderFromGlobal());
      },
    });
  },

  onRetryLogin() {
    const app = getApp();
    app.logout();
    this.setData({ loginReady: false, loginError: '' });
    app.silentLogin().then(() => this._renderFromGlobal());
  },
});
