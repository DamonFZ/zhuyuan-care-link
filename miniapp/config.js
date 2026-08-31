/**
 * CareLink 竹苑益家人 小程序基座配置
 * 所有 API 请求地址与全局常量集中在此处维护
 */
const config = {
  // API 基础域名（开发联调环境；线上发布请替换为 HTTPS 备案域名）
  BASE_URL: 'http://carelink.damon.com',

  // Token 在 Storage 中的 Key
  TOKEN_KEY: 'token',

  // 用户信息 / 角色 Key
  USER_KEY: 'userInfo',
  ROLE_KEY: 'role',
  POINTS_KEY: 'points',
  VOLUNTEER_LEVEL_KEY: 'volunteerLevel',
};

export default config;
module.exports = config;
