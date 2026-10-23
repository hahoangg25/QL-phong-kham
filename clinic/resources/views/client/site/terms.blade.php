@extends('client.layouts.site')

@section('title', 'Điều khoản & bảo mật dữ liệu')
@section('page_title', 'Điều khoản & bảo vệ dữ liệu cá nhân')
@section('page_subtitle', 'Điều khoản sử dụng dịch vụ và chính sách xử lý dữ liệu cá nhân, dữ liệu sức khỏe')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-9">
          <div class="card card-soft">
            <div class="card-body p-4 p-md-5 terms-content">
              <p class="text-muted small">Cập nhật lần cuối: {{ now()->format('d/m/Y') }} · Áp dụng cho cổng đặt lịch và hệ thống quản lý của {{ config('clinic.name') }}.</p>

              <div class="alert alert-light border small">
                <i class="bi bi-info-circle text-primary me-1"></i>
                Chính sách được xây dựng theo tinh thần <strong>Luật Bảo vệ dữ liệu cá nhân 2025</strong> (có hiệu lực từ 01/01/2026)
                và các quy định của Bộ Y tế về hồ sơ bệnh án điện tử. Người đọc nên tra cứu văn bản pháp luật hiện hành
                để có nội dung chính xác và đầy đủ nhất.
              </div>

              <h2 class="h5 mt-4">1. Phạm vi và đối tượng áp dụng</h2>
              <p>Điều khoản áp dụng cho mọi người dùng cổng bệnh nhân (đăng ký tài khoản, đặt lịch, tra cứu kết quả) và
                nhân viên phòng khám sử dụng hệ thống quản lý nội bộ. Khi đăng ký tài khoản, bạn xác nhận đã đọc, hiểu và
                đồng ý với điều khoản này cho bản thân và các hồ sơ người thân do bạn quản lý.</p>

              <h2 class="h5 mt-4">2. Mục đích xử lý dữ liệu</h2>
              <ul>
                <li>Đặt, xác nhận, nhắc lịch và quản lý lịch hẹn khám bệnh.</li>
                <li>Tiếp đón, khám, chẩn đoán, chỉ định cận lâm sàng, kê đơn và theo dõi điều trị.</li>
                <li>Lập hóa đơn, thanh toán (bao gồm thanh toán trực tuyến qua cổng thanh toán).</li>
                <li>Gửi thông báo liên quan đến dịch vụ qua email, tin nhắn và trong ứng dụng.</li>
                <li>Thống kê, báo cáo quản trị nội bộ ở dạng tổng hợp, không định danh cá nhân.</li>
              </ul>
              <p>Phòng khám <strong>không</strong> sử dụng dữ liệu cho mục đích quảng cáo và không bán dữ liệu cho bên thứ ba.</p>

              <h2 class="h5 mt-4">3. Dữ liệu được thu thập</h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 rounded bg-light h-100">
                    <div class="fw-semibold mb-1"><i class="bi bi-person-vcard me-1 text-primary"></i>Dữ liệu cá nhân cơ bản</div>
                    <div class="small">Họ tên, ngày sinh, giới tính, số điện thoại, email, địa chỉ, số CCCD, số thẻ BHYT, mối quan hệ với chủ tài khoản.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded bg-light h-100">
                    <div class="fw-semibold mb-1"><i class="bi bi-heart-pulse me-1 text-danger"></i>Dữ liệu cá nhân nhạy cảm</div>
                    <div class="small">Tình trạng sức khỏe, tiền sử bệnh, dị ứng, sinh hiệu, chẩn đoán, kết quả xét nghiệm, chẩn đoán hình ảnh, đơn thuốc.</div>
                  </div>
                </div>
              </div>
              <p class="mt-3 small text-muted">Dữ liệu sức khỏe thuộc nhóm dữ liệu cá nhân nhạy cảm và chỉ được xử lý khi có sự đồng ý của chủ thể dữ liệu hoặc trong các trường hợp pháp luật cho phép.</p>

              <h2 class="h5 mt-4">4. Quyền của chủ thể dữ liệu</h2>
              <ul>
                <li>Được biết về việc xử lý dữ liệu và xem lại điều khoản này bất cứ lúc nào.</li>
                <li>Được truy cập, xem lịch sử khám, đơn thuốc, kết quả của mình và người thân do mình quản lý.</li>
                <li>Được yêu cầu chỉnh sửa thông tin hành chính không chính xác (tự cập nhật trong tài khoản hoặc tại quầy lễ tân).</li>
                <li>Được rút lại sự đồng ý, yêu cầu hạn chế xử lý hoặc xóa dữ liệu, trừ dữ liệu bệnh án phải lưu trữ theo quy định chuyên ngành.</li>
                <li>Được khiếu nại khi quyền lợi bị xâm phạm qua hotline {{ config('clinic.hotline') }} hoặc email {{ config('clinic.email') }}.</li>
              </ul>

              <h2 class="h5 mt-4">5. Biện pháp bảo vệ dữ liệu</h2>
              <ul>
                <li><strong>Mã hóa:</strong> kết nối HTTPS; mật khẩu được băm một chiều; trường dữ liệu nhạy cảm (tiền sử bệnh, dị ứng) được mã hóa khi lưu trữ.</li>
                <li><strong>Phân quyền chặt chẽ (RBAC):</strong> mỗi nhân viên chỉ truy cập chức năng theo vai trò; chỉ bác sĩ phụ trách lượt khám được ghi bệnh án.</li>
                <li><strong>Nhật ký truy cập (audit log):</strong> mọi thao tác xem, sửa hồ sơ y tế và chuyển trạng thái đều được ghi lại người thực hiện và thời điểm.</li>
                <li><strong>Toàn vẹn bệnh án:</strong> bệnh án đã hoàn thành bị khóa, chỉ được ghi bổ sung và có ghi vết.</li>
                <li><strong>Xác thực:</strong> mã OTP khi đăng ký, đặt lịch và đặt lại mật khẩu; tài khoản có thể bị khóa khi phát hiện vi phạm.</li>
                <li><strong>Sao lưu:</strong> cơ sở dữ liệu được sao lưu định kỳ.</li>
              </ul>

              <h2 class="h5 mt-4">6. Chia sẻ dữ liệu</h2>
              <p>Dữ liệu chỉ được chia sẻ với: đơn vị cung cấp dịch vụ gửi email/SMS và cổng thanh toán (chỉ thông tin cần thiết
                cho giao dịch); cơ quan nhà nước có thẩm quyền theo yêu cầu hợp pháp.</p>

              <h2 class="h5 mt-4">7. Thời gian lưu trữ</h2>
              <p>Hồ sơ bệnh án được lưu trữ theo thời hạn quy định của Bộ Y tế đối với hồ sơ bệnh án. Dữ liệu tài khoản được lưu
                trong thời gian bạn sử dụng dịch vụ; nhật ký hệ thống được lưu tối đa 12 tháng.</p>

              <h2 class="h5 mt-4">8. Quy định đặt lịch</h2>
              <ul>
                <li>Đặt lịch trước giờ khám tối thiểu {{ setting('booking_min_hours_before') }} giờ và không quá {{ setting('booking_max_days_ahead') }} ngày.</li>
                <li>Mỗi hồ sơ chỉ có một lịch đang hoạt động trong cùng chuyên khoa, cùng ngày.</li>
                <li>Hủy miễn phí nếu trước giờ khám {{ setting('cancel_free_hours_before') }} giờ; hủy trễ hơn hoặc không đến được tính một lần vắng mặt.</li>
                <li>Vắng mặt {{ setting('no_show_threshold') }} lần, tài khoản sẽ {{ setting('no_show_policy') === 'block' ? 'tạm bị khóa đặt lịch online' : 'phải đặt cọc '.money(setting('deposit_amount')).' khi đặt lịch' }}.</li>
                <li>Check-in trễ quá {{ setting('late_checkin_minutes') }} phút so với giờ hẹn sẽ được xếp cuối hàng đợi.</li>
              </ul>

              <h2 class="h5 mt-4">9. Liên hệ</h2>
              <p class="mb-0">{{ config('clinic.name') }} · {{ config('clinic.address') }}<br>
                Hotline: {{ config('clinic.hotline') }} · Email: {{ config('clinic.email') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
