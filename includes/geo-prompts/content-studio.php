<?php

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'intent' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Phân tích theo nhu cầu thực tế của người đọc, không theo mục tiêu thao túng thứ hạng.
- Mở rộng truy vấn thành các câu hỏi tiếp nối hợp lý: định nghĩa, lựa chọn, so sánh, quy trình, chi phí, thời gian, rủi ro và hành động tiếp theo; chỉ giữ nhóm phù hợp với chủ đề.
- Xác định entities chính, quan hệ giữa các entities, information gain có thể tạo và những dữ kiện cần bằng chứng.
- Tách rõ dữ kiện đã được cung cấp, suy luận hợp lý, thông tin có nguy cơ lỗi thời và thông tin chưa xác minh.
- Không bịa nguồn, URL, số liệu, ngày tháng, khách hàng, giá, case study hoặc tuyên bố đang xếp hạng.
PROMPT,
    'outline' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Các quy tắc dưới đây được ưu tiên hơn ví dụ hoặc quota heading cố định trong prompt gốc. Không sao chép cấu trúc của ví dụ On-page SEO nếu chủ đề hiện tại không liên quan.
- Xây dựng H2/H3 theo hành trình giải quyết vấn đề và các sub-intent thực tế; không ép tỷ lệ 80/20, số heading hoặc số mục cố định.
- Với mỗi phần, nêu câu hỏi cần trả lời, câu trả lời cốt lõi, entity liên quan, bằng chứng cần dùng và định dạng phù hợp.
- Đặt các phần có khả năng trả lời trực tiếp truy vấn phức tạp, so sánh, quy trình, checklist hoặc rủi ro ở vị trí dễ tìm.
- Mỗi claim quan trọng phải gắn với dữ kiện thương hiệu, nguồn đã được cung cấp hoặc được đánh dấu cần xác minh. Không tự tạo citation hay URL.
- Không tạo H1 trong Outline xuất bản vì WordPress sử dụng tiêu đề bài làm H1.
PROMPT,
    'content' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Viết people-first và theo đúng chủ đề hiện tại. Bỏ qua mọi ví dụ, công cụ, case study hoặc chỉ dẫn On-page SEO trong prompt gốc nếu không liên quan.
- Sau mỗi H2/H3 quan trọng, trả lời câu hỏi cốt lõi ngay bằng 1 đoạn ngắn, rõ nghĩa, rồi mới giải thích sâu bằng quy trình, tiêu chí, bảng hoặc checklist.
- Mỗi đoạn tập trung vào một ý hoặc một claim; dùng tên entities nhất quán và giải thích thuật ngữ khi xuất hiện lần đầu.
- Chỉ dùng dữ kiện thương hiệu và nguồn thực sự có trong đầu vào. Claim có số liệu, giá, mốc thời gian hoặc kết quả phải có bằng chứng tương ứng; nếu thiếu thì bỏ claim hoặc diễn đạt rõ là cần xác minh.
- Không bịa citation, tên nguồn, URL, nghiên cứu, ngày cập nhật hoặc lời chứng thực.
- Tạo giá trị nguyên bản bằng kinh nghiệm, quy trình, checklist, tiêu chí quyết định và cách xử lý rủi ro đã được cung cấp; tránh đoạn văn chung chung có thể dùng cho mọi doanh nghiệp.
- Chỉ xuất HTML nội dung, không tạo H1 và không nhắc tới AI Overview, GEO, prompt hoặc mục tiêu xếp hạng.
PROMPT,
    'seo' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Giữ nguyên schema JSON và đúng số key mà prompt gốc yêu cầu.
- Title và meta phải mô tả chính xác câu trả lời hoặc giá trị chính của trang, dùng tên entity nhất quán với nội dung và không clickbait.
- Không tuyên bố nội dung được tối ưu hoặc bảo đảm xuất hiện trong AI Overview hay công cụ AI.
- Image prompt và image alt phải phản ánh đúng chủ đề thực tế, không thêm người, thương hiệu hoặc hoạt động chưa có căn cứ.
PROMPT,
    'rewrite' => <<<'PROMPT'
## Bổ sung riêng cho chế độ Rewrite khi bật AI Overview/GEO
- Không mặc định xem bài cũ là nguồn đúng. Phân loại thông tin thành: giữ lại, cần cập nhật, cần loại bỏ và cần xác minh.
- Kiểm tra kỹ dữ liệu có ngày tháng, giá, số liệu, địa điểm, chính sách, công cụ hoặc xu hướng vì có nguy cơ lỗi thời.
- Giữ lại trải nghiệm thực tế, citation, external link và thông tin độc quyền còn hợp lệ; không làm mất ngữ cảnh chỉ để thay đổi câu chữ.
- Giữ slug hiện tại trừ khi người dùng chủ động quyết định đổi; không tự bịa nguồn để hợp thức hóa nội dung cũ.
PROMPT,
);
