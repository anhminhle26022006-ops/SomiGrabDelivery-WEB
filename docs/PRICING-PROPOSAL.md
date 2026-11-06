# Cấu hình báo giá — ĐÃ DUYỆT 05/11/2026

Người dùng đã duyệt toàn bộ phương án ngày 05/11/2026 và chốt phạm vi **TP. Hồ Chí Minh**. Migration và bảng giá đã được áp dụng vào database cấu hình của project; hai policy VND/USD version 1 đang active. Chạy lại migration không tạo thêm policy. Nội dung bên dưới lưu lại bản đề xuất đã được duyệt. Không tự đăng ký/mua dịch vụ; kết nối provider thật còn cần GOOGLE_MAPS_API_KEY (Routes + Geocoding) và OPEN_METEO_API_KEY trong môi trường.

## Phương án đề xuất v1

- Phục vụ đơn xe máy tại TP. Hồ Chí Minh, quãng đường đường bộ dưới 20 km. Reverse geocode cả hai điểm; địa chỉ country VN do client gửi không tự chứng minh nằm trong phạm vi phục vụ.
- Dùng giá cố định theo band km để khớp cấu trúc distance_price_bands đang có; công thức `ceil(giá_band × hệ_số_giờ × hệ_số_thời_tiết)`. Không mặc định hiểu amount_minor là giá mỗi km.
- Giá VND và USD quản lý độc lập. Không quy đổi USD từ tỷ giá ngầm. VND lưu đồng, USD lưu cent; chỉ làm tròn lên một lần sau phép nhân.

| Khoảng đường bộ | Giá VND đề xuất | Giá USD đề xuất |
|---|---:|---:|
| 0 ≤ km < 3 | 20.000đ | $0,99 (99 cent) |
| 3 ≤ km < 5 | 25.000đ | $1,29 (129 cent) |
| 5 ≤ km < 10 | 40.000đ | $1,99 (199 cent) |
| 10 ≤ km < 15 | 60.000đ | $2,99 (299 cent) |
| 15 ≤ km < 20 | 80.000đ | $3,99 (399 cent) |

Mốc đầu được tính, mốc cuối không được tính: đúng 3 km sang band 3–5 km. Cách tính này có bước nhảy giá tại ranh giới; nếu muốn cước tăng đều theo mỗi km, cần duyệt rule khác và mở rộng cấu trúc giá trước khi áp dụng. Từ 20 km trở lên hoặc ngoài vùng phục vụ: đề xuất trả lỗi OUTSIDE_SERVICE_AREA rõ ràng, không tự dùng band cuối. Việc kiểm tra vùng/giới hạn quãng đường này là thay đổi cấu hình/nghiệp vụ tiếp theo sau duyệt, chưa triển khai trong API v1 hiện tại.

## Hệ số và phí đề xuất

Giờ địa phương Asia/Ho_Chi_Minh, áp dụng cả tuần; các band rời nhau:

| Khung giờ | Hệ số đề xuất |
|---|---:|
| 00:00–07:00 | 1,00 |
| 07:00–09:00 | 1,20 |
| 09:00–17:00 | 1,00 |
| 17:00–20:00 | 1,20 |
| 20:00–00:00 | 1,00 |

- Thời tiết tại điểm lấy: không mưa/mây/sương 1,00; mưa nhẹ 1,10; mưa vừa/nặng 1,20. Dông/ngập hoặc điều kiện nguy hiểm: đề xuất tạm ngưng nhận đơn; cần thống nhất nhóm mã thời tiết/ngưỡng và triển khai sau duyệt. Không dùng surcharge như xác nhận an toàn giao hàng.
- Phí hoàn cố định: đề xuất 10.000đ hoặc 50 cent, lưu riêng khi báo giá; chỉ trừ khi thực hiện nghiệp vụ hoàn đã chốt, không cộng vào cước giao ban đầu.
- Customer hủy trước Shipper nhận: 0. Sau assignment không dùng endpoint Customer cancel hiện tại.
- Chưa áp dụng phụ phí cân nặng, loại kiện, VAT hoặc phụ phí khác; cần nhóm xác nhận giá trên là giá khách trả và giới hạn hàng hóa trước khi vận hành. Chưa đề xuất phần trăm thu nhập/payout trong đợt này.

Ví dụ đề xuất: 4 km, 08:00, mưa nhẹ → VND `ceil(25000 × 1.20 × 1.10) = 33000`; USD `ceil(129 × 1.20 × 1.10) = 171 cent`. Không dùng tỷ giá để suy ra một bảng từ bảng kia.

## Nguồn dữ liệu đề xuất

1. **Google Routes API / TWO_WHEELER** cho quãng đường và thời gian xe máy; tài liệu Google liệt kê Việt Nam được hỗ trợ. Dùng distanceMeters/duration từ server, không lấy khoảng cách người dùng gửi hoặc đường chim bay. Chỉ kết nối sau khi duyệt provider, tài khoản/hạn mức và cách sử dụng dữ liệu. Nguồn: [Google coverage](https://developers.google.com/maps/documentation/routes/coverage-two-wheeled), [computeRoutes response](https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRoutes).
2. **Open-Meteo** cho weather_code tại điểm lấy; dùng adapter server để chuẩn hóa mã. Tài liệu mô tả current conditions dựa trên dữ liệu model 15 phút. Production thương mại dùng gói có commercial licence, không mặc định dùng free endpoint phi thương mại. Nguồn: [Weather docs](https://open-meteo.com/en/docs), [Pricing/commercial use](https://open-meteo.com/en/pricing).
3. Đã viết GoogleMeteoPricingProvider tổng hợp nguồn routing/weather và Google Geocoding kiểm tra TP.HCM; API đặt đơn chỉ biết PricingContextProvider. API keys nằm trong biến môi trường server, không gửi về browser. Chưa thể gọi thật vì môi trường chưa có key. Geocoding API phải được bật cùng Routes API trên Google key.

Đề xuất vận hành: quote giữ 5 phút; provider tổng timeout 10s; không fallback dữ liệu giả. Adapter phải phân biệt thời điểm tạo context với thời điểm dữ liệu model thời tiết: đề xuất dữ liệu thời tiết tối đa 30 phút, context được xác nhận trong 5 phút. Chi tiết mapping mã, ngân sách/call limit và retention dữ liệu provider cần chốt cùng việc duyệt nguồn; không tự đăng ký gói hoặc bật billing.

## Nội dung đã duyệt

1. Dùng phương án giá band và các mức VND/USD trên hay sửa mức nào.
2. Khung giờ, hệ số thời tiết, phí hoàn và giới hạn dưới 20 km/vùng phục vụ.
3. Google Routes TWO_WHEELER + Open-Meteo và ngân sách/hạn mức API dự kiến.

Đã triển khai kiểm tra TP.HCM, mapping thời tiết, adapter và release bảng giá có version/audit; đã áp dụng migration. Phần còn thiếu để chạy thật: API keys và danh mục kiện hàng được phép. Không đăng ký/mua gói dịch vụ hoặc tự bật billing.
