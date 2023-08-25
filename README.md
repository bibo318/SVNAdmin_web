# SVNAdmin - web-based SVN management system

### 1. Giới thiệu
- SVNAdmin is a **web program** that manages SVN on the server side through a graphical interface.

- Under normal circumstances, to configure the personnel permissions of the SVN warehouse, you need to log in to the server to manually modify the authz and passwd files. When the warehouse structure and personnel permissions are scaled up, manual management becomes very error-prone. This system can identify personnel and permissions And provide management and expansion functions.

- SVNAdmin supports **SVN protocol checkout, HTTP protocol checkout**, and supports switching between the two protocols, and supports docker deployment or source code deployment.
  - [Link code GitHub](https://github.com/bibo318/SVNAdmin) 
  
## VietSub
- SVNAdmin là **chương trình web** quản lý SVN phía máy chủ thông qua giao diện đồ họa.

- Trong trường hợp thông thường, để cấu hình quyền nhân sự của kho SVN, bạn cần đăng nhập vào máy chủ để sửa đổi thủ công các tệp authz và passwd.Khi cấu trúc kho và quyền nhân sự được mở rộng, việc quản lý thủ công trở nên rất dễ xảy ra lỗi . Hệ thống này có thể xác định nhân sự và quyền và cung cấp các chức năng quản lý và mở rộng.

- SVNAdmin hỗ trợ **kiểm tra giao thức SVN, kiểm tra giao thức HTTP**, hỗ trợ chuyển đổi giữa hai giao thức và hỗ trợ triển khai docker hoặc triển khai mã nguồn.

- SVNAdmin hỗ trợ **truy cập LDAP**, để đạt được mục đích sử dụng cơ cấu nhân sự và quy tắc phân nhóm ban đầu.

  - [Link code GitHub](https://github.com/bibo318/SVNAdmin) 
 - demo1
  ![](image/demo1.png)
 - demo2
  ![](image/demo2.png)


### 2. Khả năng tương thích (Compatibility)

**docker > CentOS7 > CentOS8 > Rocky > Ubuntu**>.............

docker > CentOS7 > CentOS8 > Rocky > Ubuntu>...........

If required under Windows, you can use the docker version

PHP version: [php5.5, php8.2] (development is based on php7.4 so it is recommended to use php7.4)

Database: SQLite, MySQL

Subversion：1.8+


### 3. Cài đặt tham khảo bản gốc - Original Reference Settings (Chinese)

- [Link GitHub](https://github.com/witersen/SvnAdminV2.0)   
 


