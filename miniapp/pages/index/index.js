/**
 * 首页占位
 * 展示当前登录态 / 角色 / 消费金余额
 */
Page({
  data: {
    loginReady: false,
    loginError: '',
    userInfo: null,
    roleText: '',
    points: '0.00',
    volunteerLevelName: '',
  },

  onLoad() {
    const app = getApp();
    // 等待 onLaunch 中静默登录结果（兼容同步到首页的时机）
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
    });
  },

  onRetryLogin() {
    const app = getApp();
    app.logout();
    this.setData({ loginReady: false, loginError: '' });
    app.silentLogin().then(() => this._renderFromGlobal());
  },
});
