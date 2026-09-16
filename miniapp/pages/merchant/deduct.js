/**
 * pages/merchant/deduct.js —— 商户扣款确认页
 *
 * 入参（URL query）：id / name / avatar / points（来自 /api/qrcode/resolve）
 * 流程：
 *   1) 展示被核销居民的姓名 + 当前余额
 *   2) 商户输入金额（digit 键盘）
 *   3) 确认扣款 → POST /api/merchant/deduct {target_user_id, amount}
 *   4) 成功 → toast → 1.5s 后返回商户首页（navigateBack 回到 home）
 */
const { request } = require('../../utils/request.js');

Page({
  data: {
    targetUser: { id: 0, name: '', avatar: '', points: 0 },
    amount: '',
    submitting: false,
  },

  onLoad(query) {
    const targetUser = {
      id: parseInt(query.id || '0', 10),
      name: decodeURIComponent(query.name || ''),
      avatar: decodeURIComponent(query.avatar || ''),
      points: parseFloat(query.points || '0') || 0,
    };
    this.setData({ targetUser });
  },

  onAmountInput(e) {
    // 限制最多 2 位小数
    let v = e.detail.value || '';
    v = v.replace(/[^\d.]/g, '');
    const parts = v.split('.');
    if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
    if (parts[1] && parts[1].length > 2) {
      v = parts[0] + '.' + parts[1].slice(0, 2);
    }
    this.setData({ amount: v });
  },

  onQuickAmount(e) {
    const a = e.currentTarget.dataset.amount;
    this.setData({ amount: String(a) });
  },

  onDeduct() {
    const amount = parseFloat(this.data.amount || '0');
    if (!amount || amount <= 0) {
      wx.showToast({ title: '请输入有效金额', icon: 'none' });
      return;
    }
    if (amount > this.data.targetUser.points) {
      wx.showModal({
        title: '余额不足',
        content: `居民当前余额 ¥${this.data.targetUser.points}，无法扣款 ¥${amount}`,
        showCancel: false,
        confirmColor: '#3b6291',
      });
      return;
    }

    const targetUserId = this.data.targetUser.id;
    if (!targetUserId) {
      wx.showToast({ title: '用户信息缺失，请重新扫码', icon: 'none' });
      return;
    }

    this.setData({ submitting: true });

    request.post('/api/merchant/deduct', {
      target_user_id: targetUserId,
      amount,
    })
      .then((res) => {
        wx.showToast({
          title: `已核销 ¥${amount}`,
          icon: 'success',
          duration: 1500,
        });
        // 1.5 秒后返回商户首页（home 页）
        setTimeout(() => {
          wx.navigateBack({ delta: 1 });
        }, 1500);
      })
      .catch((err) => {
        // 后端会返回明确错误（余额不足/参数错误等），request 已自动 toast
        console.warn('[deduct] fail:', err);
      })
      .finally(() => {
        this.setData({ submitting: false });
      });
  },
});
