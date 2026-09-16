/**
 * components/privacy-popup —— 全局隐私授权弹窗
 *
 * 机制：
 *   1) 组件 attached 时监听 wx.onNeedPrivacyAuthorization
 *      当小程序调用需要授权的 API（如 chooseAvatar / chooseMedia / getUserProfile）时，
 *      若用户尚未同意隐私协议，微信会触发该回调，此时展示弹窗。
 *   2) 用户点击「同意」按钮（open-type="agreePrivacyAuthorization"）：
 *      微信会解析此次回调，并自动放行本次被拦截的 API 调用。
 *   3) 用户点击「拒绝」：关闭弹窗，被拦截的 API 调用将失败。
 */
Component({
  data: {
    visible: false,
  },

  lifetimes: {
    attached() {
      // 监听隐私授权拦截事件
      if (typeof wx.onNeedPrivacyAuthorization === 'function') {
        wx.onNeedPrivacyAuthorization((resolve) => {
          this._resolve = resolve;
          this.setData({ visible: true });
        });
      }
    },

    detached() {
      // 取消监听（避免重复注册）
      if (typeof wx.offNeedPrivacyAuthorization === 'function') {
        try { wx.offNeedPrivacyAuthorization(); } catch (e) { /* ignore */ }
      }
    },
  },

  methods: {
    /** 阻止遮罩触摸穿透 */
    noop() {},

    /** 用户点击「同意」—— 微信 open-type="agreePrivacyAuthorization" 会自动触发 */
    onAgree() {
      this.setData({ visible: false });
      if (this._resolve) {
        // 微信官方要求：resolve({ event: 'agree' }) 告知系统用户已同意
        this._resolve({ event: 'agree' });
        this._resolve = null;
      }
    },

    /** 用户点击「拒绝 */
    onDisagree() {
      this.setData({ visible: false });
      if (this._resolve) {
        this._resolve({ event: 'disagree' });
        this._resolve = null;
      }
      wx.showToast({ title: '已拒绝隐私授权', icon: 'none' });
    },

    /** 点击隐私协议链接，跳转到微信官方隐私保护指引页 */
    onOpenPrivacy() {
      if (typeof wx.openPrivacyContract === 'function') {
        wx.openPrivacyContract({
          fail: () => wx.showToast({ title: '打开失败', icon: 'none' }),
        });
      }
    },
  },
});
