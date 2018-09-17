# Class Duty Dispatcher

```bash
# Project setup
yarn install

# Compiles and hot-reloads for development
yarn run serve

# Compiles and minifies for production
yarn run build
```

## TODO

- [x] 随机抽取，根据扫地次数（上午/中午/晚上，一天 3 扫），均匀安排

- [-] 扫地次数不同（周日只扫 1 次，平时一日 3 扫）

- [ ] 班上 53 个人，工区需要的人多，半个周人数可能就会用完，所以一个人一周可能要扫两次

- [ ] 教室 工区，轮换着扫

- [ ] 节假日，删除扫地记录，重新生成

- [ ] 合适的 教室/公区 成员数

如果 教室:公区 不是 1:1，教室 工区，轮换着扫 会出现问题

```php
// 上次扫工区，这次就不再扫工区
$canArea = getMemberNextArea($name);
if (!is_null($canArea) && $canArea !== $areaName) {
    unset($canUseMembers[$k]);
    continue;
}
```

![20180916232611](https://user-images.githubusercontent.com/22412567/45598053-028b7a00-ba08-11e8-87f1-4bfa3437de4b.jpg)
