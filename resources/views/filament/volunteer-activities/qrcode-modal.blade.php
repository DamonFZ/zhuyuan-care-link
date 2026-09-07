<div style="display:flex; flex-direction:column; align-items:center; gap:16px; padding:8px;">
    <div style="font-size:18px; font-weight:600;">{{ $title }}</div>
    <img src="{{ $qrImage }}" alt="活动二维码" style="width:320px; height:320px; background:#fff;" />
    <div style="color:#6b7280; font-size:13px;">
        请将此二维码打印或投屏至活动现场，志愿者扫码签到/签退。
    </div>
    <div style="color:#9ca3af; font-size:12px; word-break:break-all; max-width:100%;">
        token：{{ $token }}
    </div>
</div>
