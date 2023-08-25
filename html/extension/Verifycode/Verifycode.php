<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

class Verifycode
{

    //chiều rộng hình ảnh tính bằng pixel
    private $imageWidth;

    //chiều cao hình ảnh tính bằng pixel
    private $imageHeight;

    //mã xác nhận
    private $code;

    //đường dẫn lưu file
    private $file;

    function __construct($imageWidth, $imageHeight, $code)
    {
        $this->imageWidth = $imageWidth;
        $this->imageHeight = $imageHeight;
        $this->code = $code;
        //$this->file = $file;
    }

    function CreateVerifacationImage()
    {

        //Chức năng đặt kích thước captcha
        $image = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        //Mã xác minh màu RGB là (255,255,255)#ffffff
        $bgcolor = imagecolorallocate($image, 255, 255, 255);

        //điền vào khu vực
        imagefill($image, 0, 0, $bgcolor);

        $codeArray = str_split($this->code);

        for ($i = 0; $i < count($codeArray); $i++) {

            //đặt cỡ chữ
            $fontsize = 10;

            //Số càng lớn thì màu càng nhạt, ở đây là màu đậm hơn 0-120
            //0-255 tùy chọn
            $fontcolor = imagecolorallocate($image, rand(40, 150), rand(40, 150), rand(40, 150));

            //Nội dung mã xác minh
            $fontcontent = $codeArray[$i];

            //tọa độ ngẫu nhiên
            $x = ($i * 150 / 4) + rand(5, 10);
            $y = rand(5, 10);

            imagestring($image, $fontsize, (int)$x, (int)$y, $fontcontent, $fontcolor);
        }

        //Đặt các phần tử giao thoa, đặt điểm bông tuyết
        for ($i = 0; $i < 300; $i++) {

            //Đặt màu, 20-200 màu nhạt hơn số, không cản trở việc đọc
            $inputcolor = imagecolorallocate($image, rand(50, 200), rand(20, 200), rand(50, 200));

            //vẽ một phần tử pixel
            imagesetpixel($image, rand(1, 149), rand(1, 39), $inputcolor);
        }

        //Thêm các phần tử can thiệp và đặt đường ngang (đặt màu của đường trước, sau đó đặt đường ngang)
        for ($i = 0; $i < 4; $i++) {

            //thiết lập màu của dòng
            $linecolor = imagecolorallocate($image, rand(20, 220), rand(20, 220), rand(20, 220));

            imageline($image, rand(1, 149), rand(1, 39), rand(1, 299), rand(1, 149), $linecolor);
        }

        //Tạo đầu ra hàm png vào tệp
        ob_start();

        imagepng($image);

        $imageString = base64_encode(ob_get_contents());

        //kết thúc chức năng đồ họa, loại bỏ $image
        imagedestroy($image);

        ob_end_clean();

        return 'data:image/png;base64,' . $imageString;
    }
}
