/**
 * 小程序网络请求封装
 * - 自动拼接 BASE_URL
 * - 请求前自动注入 Authorization: Bearer ${token}
 * - 响应后统一拦截 401 → 清除本地失效 token，并回调上层重新登录
 */
const config = require('../config.js');

/**
 * 通用请求
 * @param {Object} options
 * @param {string} options.url       接口路径（如 /wechat/login）
 * @param {string} [options.method='GET']
 * @param {Object} [options.data]    请求体 / query
 * @param {Object} [options.header]  自定义 header（会与鉴权 header 合并）
 * @param {boolean} [options.auth=true] 是否需要携带 token（登录接口本身传 false）
 */
const request = (options = {}) => {
  const {
    url,
    method = 'GET',
    data = {},
    header = {},
    auth = true,
  } = options;

  if (!url) {
    return Promise.reject(new Error('[request] url 不能为空'));
  }

  // 拼完整 URL（如果已经是 http/https 开头就不再拼）
  let fullUrl = url;
  if (!/^https?:\/\//.test(url)) {
    fullUrl = config.BASE_URL.replace(/\/$/, '') + (url.startsWith('/') ? url : '/' + url);
  }

  const finalHeader = Object.assign({}, header);

  // 自动注入 Token
  if (auth) {
    const token = wx.getStorageSync(config.TOKEN_KEY);
    if (token) {
      finalHeader['Authorization'] = 'Bearer ' + token;
      finalHeader['Accept'] = 'application/json';
    }
  }

  return new Promise((resolve, reject) => {
    wx.request({
      url: fullUrl,
      method: method.toUpperCase(),
      data,
      header: finalHeader,
      success: (res) => {
        // -------- 401 未授权：清理失效 token --------
        if (res.statusCode === 401) {
          wx.removeStorageSync(config.TOKEN_KEY);
          wx.removeStorageSync(config.ROLE_KEY);
          wx.removeStorageSync(config.USER_KEY);
          wx.removeStorageSync(config.POINTS_KEY);
          wx.removeStorageSync(config.VOLUNTEER_LEVEL_KEY);
          wx.showToast({
            title: '登录已失效，请重新打开小程序',
            icon: 'none',
            duration: 2000,
          });
          // 业务层可 catch 该错误并跳回首页/重新拉起登录
          return reject({
            code: 401,
            message: 'Unauthorized：token 已被系统清理',
            raw: res,
          });
        }

        // -------- 兼容后端 {code, message, data} 统一包装 --------
        const body = res.data || {};
        if (typeof body === 'object' && 'code' in body) {
          if (body.code === 200 || body.code === 0) {
            return resolve({
              httpStatus: res.statusCode,
              ...body,
            });
          }
          wx.showToast({
            title: body.message || '请求失败',
            icon: 'none',
          });
          return reject({
            code: body.code,
            message: body.message,
            raw: res,
          });
        }

        // 非统一格式（纯数据）直接透传
        resolve({
          httpStatus: res.statusCode,
          code: 200,
          message: 'success',
          data: body,
        });
      },
      fail: (err) => {
        wx.showToast({
          title: '网络连接失败',
          icon: 'none',
        });
        reject({
          code: -1,
          message: 'Network error',
          raw: err,
        });
      },
    });
  });
};

// 便捷 HTTP 方法糖
['get', 'post', 'put', 'delete', 'patch'].forEach((m) => {
  request[m] = (url, data = {}, extra = {}) =>
    request(Object.assign({ url, method: m.toUpperCase(), data }, extra));
});

module.exports = { request, default: request };
