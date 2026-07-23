<?php

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'research' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Lập query fan-out theo các sub-intent thực tế và câu hỏi tiếp nối; không tạo nhóm chỉ để tăng số lượng từ khóa.
- Với mỗi chủ đề quan trọng, xác định entity, claim có thể trả lời, bằng chứng đang có, nguồn/URL được cung cấp, độ mới và phần còn thiếu.
- Phân loại rõ: dữ kiện đã xác minh, suy luận, thông tin có nguy cơ lỗi thời và thông tin chưa xác minh.
- Đề xuất information gain từ quy trình, checklist, tiêu chí quyết định, kinh nghiệm và dữ liệu thương hiệu thực tế.
- Không dùng title/snippet SERP làm bằng chứng đầy đủ cho claim và không tự tạo citation.
PROMPT,
    'brief' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Mỗi H2/H3 cần nêu sub-intent, câu trả lời cốt lõi, entity liên quan, claim cần bằng chứng, nguồn được phép dùng và mức độ freshness cần kiểm tra.
- Tổ chức các phần định nghĩa, so sánh, quy trình, checklist, chi phí, thời gian và rủi ro thành các khối có thể hiểu độc lập khi chúng thực sự phù hợp.
- Ưu tiên đoạn trả lời trực tiếp trước phần giải thích sâu; không ép mọi heading thành câu hỏi.
- Đánh dấu rõ claim chưa đủ bằng chứng để bước Content bỏ qua thay vì tự hoàn thiện.
- Không bịa external URL, nghiên cứu, thống kê hoặc ngày cập nhật.
PROMPT,
    'outline_validation' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Kiểm tra mỗi H2 có một sub-intent riêng và tạo ra giá trị có thể hiểu độc lập; gộp phần trùng hoặc chỉ đổi cách diễn đạt.
- Bảo đảm các truy vấn so sánh, quy trình, chi phí, thời gian, rủi ro và hành động tiếp theo được phủ khi phù hợp với Research.
- Với mỗi phần quan trọng, giữ rõ câu trả lời cốt lõi, entity, bằng chứng hoặc cảnh báo cần xác minh.
- Không thêm claim, citation, URL hoặc dữ kiện mới ngoài Research, Brief và hồ sơ thương hiệu đã cung cấp.
PROMPT,
    'content' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Sau mỗi H2/H3 quan trọng, trả lời câu hỏi cốt lõi bằng một đoạn ngắn, rõ nghĩa trước khi giải thích sâu.
- Mỗi đoạn tập trung vào một ý hoặc claim; dùng tên entity nhất quán và giải thích thuật ngữ khi xuất hiện lần đầu.
- Claim có số liệu, giá, thời gian, kết quả hoặc so sánh phải được Research/Brief hỗ trợ. Nếu thiếu bằng chứng, bỏ claim hoặc nói rõ điều kiện cần xác minh.
- Chỉ dùng citation hoặc URL đã có trong đầu vào và thực sự hỗ trợ câu đang viết; không bịa nguồn.
- Ưu tiên information gain thực tế: quy trình, checklist, tiêu chí quyết định, vai trò, rủi ro và cách xử lý.
- Không nhắc AI Overview, GEO, prompt hoặc mục tiêu xếp hạng trong nội dung xuất bản.
PROMPT,
    'seo' => <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Giữ nguyên schema JSON của bước SEO.
- Title, meta, focus keyword và các entity phải nhất quán với nội dung hiển thị, không clickbait và không thêm claim mới.
- FAQ schema chỉ được lấy từ câu hỏi và câu trả lời đầy đủ đang hiển thị trong bài.
- Không tuyên bố hoặc ngụ ý bảo đảm xuất hiện trong AI Overview hay công cụ AI.
PROMPT,
    'quality' => <<<'PROMPT'
## Kiểm định bổ sung: AI Overview/GEO
- Kiểm tra direct-answer coverage: các câu hỏi quan trọng có câu trả lời ngắn, rõ và đúng trước phần giải thích sâu.
- Kiểm tra claim–evidence: claim quan trọng, số liệu, giá, thời gian, kết quả và so sánh phải có bằng chứng tương ứng trong Research/Brief.
- Kiểm tra citation: URL phải có trong đầu vào, hỗ trợ đúng claim và không được bịa. Citation không đủ căn cứ là critical issue.
- Kiểm tra freshness: cảnh báo thông tin có ngày tháng hoặc khả năng thay đổi nhưng không ghi phạm vi/thời điểm.
- Kiểm tra entity consistency, coverage của sub-intent, information gain và các đoạn chung chung có thể áp dụng cho mọi doanh nghiệp.
- Không yêu cầu sửa chỉ để lặp từ khóa hoặc biến mọi heading thành câu hỏi.
PROMPT,
);
