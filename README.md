# 校友邦实现自动签到签退和健康上报

打开 `校友邦` 小程序，默认只能 `微信登录` ，登录后在 `设置` 里 `退出登录` ，即可 **手机号登录**，可以通过 `忘记密码` 功能来重设密码。

## 填写配置

```php
$config = array(
    # 全局PUSH+推送token，留空则不推送
    'pushToken' => '',
    'userList' => array(
        array(
            # 登录账号
            'username' => '',
            # 登录密码
            'password' => '',
            'location' => array(
                'country' => '中国',
                'province' => '河南省',
                'city' => '新乡市',
                # 区县级行政区划代码，可自行百度获取
                # 貌似可以直接写身份证前6位
                'adcode' => '360000',
                # 校友邦签到地址
                'address' => '升旗台北300米',
            ),
            # 提交的时候有IP参数，暂不知用途，留空则自动获取本机IP
            'ip' => '', 
            # 健康码图片地址，留空则不提交
            'imgurl' => '',
            # PUSH+推送token，留空则使用全局token
            'pushToken' => '',
        ),
        // 多用户写法
        // array(
        //     'username' => '',
        //     'password' => '',
        //     'location' => array(
        //         'country' => '',
        //         'province' => '',
        //         'city' => '',
        //         'adcode' => '',
        //         'address' => '',
        //     ),
        //     'ip' => '', 
        //     'imgurl' => '',
        //     'pushToken' => '',
        // ),
    )
);
```

## 定时推送

我们可以把上面的 `php` 文件上传到网站中，使用宝塔计划任务进行触发，或者说写个 `shell` 脚本，`python` 脚本之类的搭配 `crontab` 定时触发，也可以上传到云函数，使用云函数的定时触发功能。

## 写在后面

具体的逻辑可以看下面的文章

- [抓包校友邦小程序实现自动签到](https://5ime.cn/xybsyw.html)
- [校友邦小程序签到加密逻辑解析](https://5ime.cn/xybsyw-re.html)