<?php

return [
    "gateway" => env("WECHAT_OAUTH_GATEWAY", "http://oauth.damon.com"),
    "miniprogram" => [
        "appid" => env("WECHAT_MINI_PROGRAM_APPID", ""),
        "secret" => env("WECHAT_MINI_PROGRAM_SECRET", ""),
    ],
];
