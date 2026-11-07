# Cước theo quãng đường và trọng lượng — ĐÃ DUYỆT

Ngày 05/11/2026. Người dùng đã duyệt công thức và thông số trong bản này bằng câu trả lời "ok". Đã triển khai backend, trang Pricing VI/EN và công cụ tính thử có trọng lượng; áp dụng migration `20261105_distance_weight_pricing` lên database cấu hình. VND/USD version 3 active, có audit; chạy lại migration không tạo thêm policy. Không thay giá hoặc snapshot của đơn cũ.

## Công thức đề xuất

```
distance_extra_km = max(0, road_distance_km - included_distance_km)
weight_extra_kg = max(0, estimated_weight_kg - included_weight_kg)
subtotal_minor = base_fee_minor
               + distance_extra_km * per_km_minor
               + weight_extra_kg * per_kg_minor
total_charge_minor = ceil(subtotal_minor * time_multiplier * weather_multiplier)
```

Tính theo số mét đường bộ và số gram thực tế trong dữ liệu đơn, không làm tròn lên từng km/kg. Chỉ làm tròn lên một lần ở tổng cuối, tới đồng VND hoặc cent USD, dùng số thập phân chính xác. Độ chính xác khoảng cách 0,001 km, khối lượng 0,001 kg. Khoảng đường trên 3 km tăng đều theo quãng đường; không còn band 15–20 km giá cố định.

## Thông số đề xuất

| Thông số | VND | USD |
|---|---:|---:|
| Cước cơ bản, gồm 3 km và 3 kg đầu | 20.000đ | 99 cent ($0,99) |
| Mỗi km vượt 3 km | 4.000đ/km | 20 cent/km ($0,20/km) |
| Mỗi kg vượt 3 kg | 2.500đ/kg | 10 cent/kg ($0,10/kg) |
| Phí hoàn, tách khỏi cước giao ban đầu | 10.000đ | 50 cent ($0,50) |

Hai bảng độc lập, không dùng tỷ giá quy đổi. Đây là mức đã được người dùng duyệt cho project; không phải khảo sát giá thị trường.

Giữ nguyên hệ số đã duyệt: giờ thường 1,00; 07–09h và 17–20h giờ Việt Nam 1,20; thời tiết bình thường 1,00, mưa nhẹ 1,10, mưa vừa/nặng 1,20. Nhân hệ số lên cả cước quãng đường và phần trọng lượng. Thời tiết nguy hiểm dừng nhận đơn. Hủy trước Shipper nhận miễn phí.

Phạm vi TP. Hồ Chí Minh, tuyến dưới 40 km theo yêu cầu mới. Đề xuất khối lượng hợp lệ >0 và tối đa 30 kg cho luồng xe máy hiện tại; ngưỡng này cũng cần được duyệt. Hàng vượt mức bị từ chối, không tự nhận rồi cộng phí. Không tự thêm phụ phí loại kiện hoặc kích thước.

## Ví dụ kiểm tra, giờ thường và thời tiết bình thường

| Đường bộ | Trọng lượng | VND | USD |
|---|---:|---:|---:|
| 3 km | 1 kg | 20.000đ | $0,99 |
| 4 km | 1 kg | 24.000đ | $1,19 |
| 15 km | 1 kg | 68.000đ | $3,39 |
| 20 km | 1 kg | 88.000đ | $4,39 |
| 15 km | 5 kg | 73.000đ | $3,59 |
| 20 km | 5 kg | 93.000đ | $4,59 |
| 39 km | 5 kg | 169.000đ | $8,39 |

Ví dụ lẻ: 4,5 km và 3,5 kg → 27.250đ / 134 cent; giờ cao điểm và mưa nhẹ → ceil(27.250 × 1,20 × 1,10) = 35.970đ; USD ceil(134 × 1,20 × 1,10) = 177 cent ($1,77).

## Các thay đổi đã triển khai

1. Thêm rule BASE_PLUS_DISTANCE_PLUS_WEIGHT cùng các tham số vào policy; không sửa nghĩa amount_minor của các band cũ.
2. Phát hành policy version mới, giữ nguyên giá/snapshot mọi đơn đã tạo.
3. API lưu đủ base fee, số km/kg miễn phí, đơn giá km/kg, phần khoảng cách, phần trọng lượng, multipliers và tổng cuối vào snapshot.
4. Đồng bộ Pricing VI/EN, công cụ tính thử và Customer demo; thêm input trọng lượng và giải thích công thức, bỏ bảng band cố định của policy mới.
5. Kiểm thử quãng đường/khối lượng lẻ, mốc 3 km/3 kg/40 km/30 kg, làm tròn đồng/cent, snapshot và hệ số giờ/thời tiết.

## Ownership theo scope 06/11/2026

Người 1 sở hữu công thức/giá/phí/policy, quote/order snapshot, payment/refund và email. Người 3 tiếp quản CRUD địa chỉ và Google Places/Geocoding/Routes, cung cấp dữ liệu để Người 1 tính giá; Người 2 dùng route/GPS cho giao nhận. Thay scope không thay giá đã duyệt. Owner transport/key Open-Meteo chưa giao riêng trong bản mới, cần nhóm chốt theo [BACKEND-SCOPE.md](BACKEND-SCOPE.md).
