/**
 * CareLink 邻舍益家人 小程序启动基座
 * - onLaunch: 静默拉起微信 wx.login → 换 code → 调后端 /wechat/login → 下发 Sanctum Token
 * - 登录成功：把 token / role / points / volunteerLevel / userInfo 完整写入 Storage + 挂 globalData
 * - 401 / 登录失败：上层业务可通过 globalData.loginReady / loginError 判断并做降级
 */
const config = require('./config.js');
const { request } = require('./utils/request.js');

App({
  globalData: {
    // 全局配置
    baseUrl: config.BASE_URL,

    // 登录态
    token: '',
    role: 'resident',
    points: 0,
    volunteerLevel: null,
    userInfo: null,

    // 启动状态
    loginReady: false,
    loginError: null,
  },

  /**
   * 启动入口：静默登录闭环
   */
  onLaunch() {
    this.silentLogin();
  },

  /**
   * 静默登录流程：wx.login → 后端 code → openid → Sanctum Token
   * @returns {Promise<Object>} 登录后后端返回的 data 段
   */
  silentLogin() {
    const app = this;
    return new Promise((resolve, reject) => {
      wx.login({
        success: (loginRes) => {
          if (!loginRes.code) {
            const msg = 'wx.login 未返回 code';
            app._setLoginError(msg);
            wx.showToast({ title: msg, icon: 'none' });
            return reject(new Error(msg));
          }

          // 调后端：该接口本身不要求带 token，传 auth:false
          request.post('/wechat/login', { code: loginRes.code }, { auth: false })
            .then((res) => {
              const data = res.data || {};
              app._persistLoginData(data);
              app.globalData.loginReady = true;
              app.globalData.loginError = null;
              resolve(data);
            })
            .catch((err) => {
              const msg = (err && err.message) || '静默登录失败';
              app._setLoginError(msg);
              reject(err);
            });
        },
        fail: (err) => {
          const msg = '调用 wx.login 失败: ' + (err.errMsg || 'unknown');
          app._setLoginError(msg);
          reject(new Error(msg));
        },
      });
    });
  },

  /**
   * 把后端返回的数据写入 Storage + globalData
   * 后端返回契约（见 AuthController::wechatLogin）：
   * { token, role, points, volunteer_hours, volunteerLevel, user }
   */
  _persistLoginData(data) {
    const { token, role, points, volunteerLevel, user } = data;

    if (token) {
      wx.setStorageSync(config.TOKEN_KEY, token);
      this.globalData.token = token;
    }

    const finalRole = role || 'resident';
    wx.setStorageSync(config.ROLE_KEY, finalRole);
    this.globalData.role = finalRole;

    const finalPoints = typeof points === 'number' ? points : 0;
    wx.setStorageSync(config.POINTS_KEY, finalPoints);
    this.globalData.points = finalPoints;

    wx.setStorageSync(config.VOLUNTEER_LEVEL_KEY, volunteerLevel || null);
    this.globalData.volunteerLevel = volunteerLevel || null;

    if (user) {
      wx.setStorageSync(config.USER_KEY, user);
      this.globalData.userInfo = user;
    }
  },

  _setLoginError(msg) {
    this.globalData.loginReady = false;
    this.globalData.loginError = msg;
  },

  /**
   * 上层页面可调用：刷新本地余额缓存（如消费后）
   */
  setPoints(points) {
    const finalPoints = typeof points === 'number' ? points : 0;
    wx.setStorageSync(config.POINTS_KEY, finalPoints);
    this.globalData.points = finalPoints;
  },

  /**
   * 退出登录（清理所有缓存）
   */
  logout() {
    wx.removeStorageSync(config.TOKEN_KEY);
    wx.removeStorageSync(config.ROLE_KEY);
    wx.removeStorageSync(config.USER_KEY);
    wx.removeStorageSync(config.POINTS_KEY);
    wx.removeStorageSync(config.VOLUNTEER_LEVEL_KEY);
    this.globalData.token = '';
    this.globalData.role = 'resident';
    this.globalData.points = 0;
    this.globalData.volunteerLevel = null;
    this.globalData.userInfo = null;
    this.globalData.loginReady = false;
  },
});
