{{-- ================================================================= --}}
{{-- BLOCK CỐ ĐỊNH: FOOTER CHÂN TRANG CỔNG BỆNH NHÂN (CLIENT)          --}}
{{-- ================================================================= --}}
<footer id="footer" class="footer-16 footer position-relative">
  <div class="container">
    <div class="footer-main" data-aos="fade-up" data-aos-delay="100">
      <div class="row align-items-start">
        <div class="col-lg-5">
          <div class="brand-section">
            <a href="{{ route('home') }}" class="logo d-flex align-items-center mb-4">
              <span class="sitename">{{ config('clinic.name') }}</span>
            </a>
            <p class="brand-description">Hệ thống Nha khoa Thẩm mỹ & Chuyên sâu Lộc Phát với đội ngũ bác sĩ răng hàm mặt giàu kinh nghiệm, trang thiết bị chẩn đoán hình ảnh CT ConeBeam hiện đại. Đặt lịch trực tuyến 24/7, không phải chờ đợi, hồ sơ nha khoa lưu trữ an toàn.</p>
            <div class="contact-info mt-5">
              <div class="contact-item"><i class="bi bi-geo-alt"></i><span>{{ config('clinic.address') }}</span></div>
              <div class="contact-item"><i class="bi bi-telephone"></i><span>{{ config('clinic.hotline') }}</span></div>
              <div class="contact-item"><i class="bi bi-envelope"></i><span>{{ config('clinic.email') }}</span></div>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="footer-nav-wrapper">
            <div class="row">
              <div class="col-6 col-lg-4">
                <div class="nav-column">
                  <h6>Phòng khám</h6>
                  <nav class="footer-nav">
                    <a href="{{ route('specialties.index') }}">Chuyên khoa</a>
                    <a href="{{ route('doctors.index') }}">Đội ngũ bác sĩ</a>
                    <a href="{{ route('services.index') }}">Bảng giá dịch vụ</a>
                    <a href="{{ route('lobby') }}" target="_blank">Màn hình hàng đợi</a>
                  </nav>
                </div>
              </div>
              <div class="col-6 col-lg-4">
                <div class="nav-column">
                  <h6>Bệnh nhân</h6>
                  <nav class="footer-nav">
                    <a href="{{ route('account.booking.create') }}">Đặt lịch khám</a>
                    <a href="{{ route('account.appointments.index') }}">Tra cứu lịch hẹn</a>
                    <a href="{{ route('account.visits.index') }}">Kết quả khám</a>
                    <a href="{{ route('register') }}">Đăng ký tài khoản</a>
                  </nav>
                </div>
              </div>
              <div class="col-6 col-lg-4">
                <div class="nav-column">
                  <h6>Hỗ trợ</h6>
                  <nav class="footer-nav">
                    <a href="{{ route('terms') }}">Điều khoản & bảo mật dữ liệu</a>
                    <a href="tel:{{ preg_replace('/\D/', '', config('clinic.hotline')) }}">Hotline {{ config('clinic.hotline') }}</a>
                    <a href="{{ route('login') }}">Đăng nhập nhân viên</a>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container">
      <div class="bottom-content">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <div class="copyright">
              <p>© {{ date('Y') }} <span class="sitename">{{ config('clinic.name') }}</span>. Đồ án Hệ thống quản lý phòng khám và đặt lịch khám trực tuyến.</p>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="legal-links">
              <a href="{{ route('terms') }}">Điều khoản sử dụng</a>
              <div class="credits">
                Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>. Distributed by <a href="https://themewagon.com" target="_blank">ThemeWagon</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>
