# MODULE WHMCS

## 1. Install module

```
Upload toàn bộ thư mục vào đường dẫn modules/servers
```

## 2. Các crontab cần cài đặt

```
1. GET TOKEN
php -q  /path/modules/servers/cloudnest/crontab.php getToken (cài đặt lúc 2h00 và 14h00)

2. Tạo VPS đang ở trạng thái chờ tạo
php -q  /path/modules/servers/cloudnest/crontab.php createVpsWaiting (cài đặt 5-10p/lần)

3. Cập nhật lại trạng thái VPS (trạng thái: đang tạo, đang cài đặt lại,...)
php -q  /path/modules/servers/cloudnest/crontab.php updateStatusVps (cài đặt 1p/lần)
```

## 2. Sản phẩm Addon VPS và Hệ điều hành
```
1. Vào Configurable Option Group
2. Đối với sản phẩm Addon VPS
    + Trong Manage Group, bạn tạo "Add new configurable option"
    + Quy tắc đặt tên: Addon CPU, Addon Disk, Addon Ram
3. Đối với Hệ điều hành
    + Trong Manage Group, bạn tạo "Add new configurable option"
    + Quy tắc đặt tên: Hệ điều hành hoặc OS
```

## 3. Các chức năng thêm
```
Cần require_once file CrontabController.php

1. Chức năng gia hạn
- Code
    $crontab = new CrontabController();
    $crontab->renewVps($serviceId, $billingCycle);
    + Trong đó: $serviceId là ID của dịch vụ, $billingCycle là thời gian thuê

- Trả về: 1 mãng
    + Thành công: error = 0
    + Thất bại: error = 1, message = string

2. Chức năng nâng cấp
- CODE
    $crontab = new CrontabController();
    $crontab->addonVps($serviceId, $addonCpu, $addonRam, $addonDisk)
    + Trong đó: $serviceId là ID của dịch vụ, $addonCpu là số CPU nâng cấp, $addonRam là số RAM nâng cấp, $addonDisk là số DISK nâng cấp ($addonDisk là số chia hết cho 10)

- Trả về: 1 mãng
    + Thành công: error = 0
    + Thất bại: error = 1, message = string

```
