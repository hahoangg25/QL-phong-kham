<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Client;
use App\Http\Controllers\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cổng thông tin công khai (khách vãng lai)
|--------------------------------------------------------------------------
*/
Route::get('/', [Client\Site\HomeController::class, 'index'])->name('home');
Route::get('/chuyen-khoa', [Client\Site\SpecialtyController::class, 'index'])->name('specialties.index');          // BN-05
Route::get('/chuyen-khoa/{specialty:slug}', [Client\Site\SpecialtyController::class, 'show'])->name('specialties.show');
Route::get('/bac-si', [Client\Site\DoctorController::class, 'index'])->name('doctors.index');                       // BN-06
Route::get('/bac-si/{doctor}', [Client\Site\DoctorController::class, 'show'])->name('doctors.show');
Route::get('/bang-gia-dich-vu', [Client\Site\ServiceController::class, 'index'])->name('services.index');           // BN-05
Route::get('/lich-trong', [Client\Site\SlotController::class, 'index'])->name('slots.index');                       // BN-07 (JSON)
Route::get('/goi-y-chuyen-khoa', [Client\Site\SymptomController::class, 'suggest'])->name('symptoms.suggest');       // BN-20 (JSON)
Route::get('/man-hinh-hang-doi', [Client\Site\LobbyController::class, 'index'])->name('lobby');                     // LT-09
Route::get('/man-hinh-hang-doi/du-lieu', [Client\Site\LobbyController::class, 'data'])->name('lobby.data');
Route::view('/dieu-khoan', 'client.site.terms')->name('terms');

/*
|--------------------------------------------------------------------------
| Xác thực (BN-01, BN-02)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/dang-nhap', [Auth\LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/dang-ky', [Auth\RegisterController::class, 'show'])->name('register');
    Route::post('/dang-ky', [Auth\RegisterController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/quen-mat-khau', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/quen-mat-khau', [Auth\PasswordResetController::class, 'sendOtp'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/dat-lai-mat-khau', [Auth\PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [Auth\PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/dang-xuat', [Auth\LoginController::class, 'logout'])->name('logout');
    Route::get('/xac-thuc-tai-khoan', [Auth\RegisterController::class, 'showVerify'])->name('verify.show');
    Route::post('/xac-thuc-tai-khoan', [Auth\RegisterController::class, 'verify'])->name('verify.submit')->middleware('throttle:10,1');
    Route::post('/xac-thuc-tai-khoan/gui-lai', [Auth\RegisterController::class, 'resend'])->name('verify.resend')->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| Thanh toán online (BN-10, HT-08)
|--------------------------------------------------------------------------
*/
Route::get('/thanh-toan/vnpay/return', [PaymentGatewayController::class, 'vnpayReturn'])->name('payment.vnpay.return');
Route::get('/thanh-toan/vnpay/ipn', [PaymentGatewayController::class, 'vnpayIpn'])->name('payment.vnpay.ipn');
Route::get('/thanh-toan/momo/return', [PaymentGatewayController::class, 'momoReturn'])->name('payment.momo.return');
Route::post('/thanh-toan/momo/ipn', [PaymentGatewayController::class, 'momoIpn'])->name('payment.momo.ipn'); // MoMo gọi server-to-server, miễn CSRF
Route::middleware('auth')->group(function () {
    Route::get('/thanh-toan/gia-lap/{payment:code}', [PaymentGatewayController::class, 'simulate'])->name('payment.simulate');
    Route::post('/thanh-toan/gia-lap/{payment:code}', [PaymentGatewayController::class, 'simulateProcess'])->name('payment.simulate.process');
});

/*
|--------------------------------------------------------------------------
| Cổng bệnh nhân (đã đăng nhập + xác thực OTP)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'verified.otp', 'role:patient'])->prefix('tai-khoan')->name('account.')->group(function () {
    Route::get('/', [Client\Account\DashboardController::class, 'index'])->name('dashboard');

    // BN-03
    Route::get('/thong-tin', [Client\Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/thong-tin', [Client\Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/doi-mat-khau', [Client\Account\ProfileController::class, 'password'])->name('profile.password');

    // BN-04
    Route::resource('ho-so', Client\Account\PatientController::class)->parameters(['ho-so' => 'patient'])->names('patients')->except('show');

    // BN-08, BN-09
    Route::get('/dat-lich', [Client\Account\BookingController::class, 'create'])->name('booking.create');
    Route::post('/dat-lich', [Client\Account\BookingController::class, 'store'])->name('booking.store')->middleware('throttle:20,1');
    Route::get('/dat-lich/xac-nhan/{hold:token}', [Client\Account\BookingController::class, 'verifyForm'])->name('booking.verify');
    Route::post('/dat-lich/xac-nhan/{hold:token}', [Client\Account\BookingController::class, 'verify'])->name('booking.confirm')->middleware('throttle:10,1');
    Route::post('/dat-lich/xac-nhan/{hold:token}/gui-lai', [Client\Account\BookingController::class, 'resend'])->name('booking.resend')->middleware('throttle:3,1');
    Route::delete('/dat-lich/xac-nhan/{hold:token}', [Client\Account\BookingController::class, 'release'])->name('booking.release');

    // BN-11, BN-12, BN-13, BN-18
    Route::get('/lich-hen', [Client\Account\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/lich-hen/{appointment}', [Client\Account\AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/lich-hen/{appointment}/hang-doi', [Client\Account\AppointmentController::class, 'queue'])->name('appointments.queue');
    Route::post('/lich-hen/{appointment}/huy', [Client\Account\AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/lich-hen/{appointment}/doi-lich', [Client\Account\AppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule');
    Route::post('/lich-hen/{appointment}/doi-lich', [Client\Account\AppointmentController::class, 'reschedule'])->name('appointments.reschedule.store');
    Route::post('/lich-hen/{appointment}/thanh-toan', [Client\Account\PaymentController::class, 'appointment'])->name('appointments.pay');

    // BN-15, BN-19
    Route::get('/lich-su-kham', [Client\Account\VisitController::class, 'index'])->name('visits.index');
    Route::get('/lich-su-kham/{visit}', [Client\Account\VisitController::class, 'show'])->name('visits.show');
    Route::post('/lich-su-kham/{visit}/danh-gia', [Client\Account\VisitController::class, 'review'])->name('visits.review');
    Route::get('/ket-qua/{labResult}/tep', [Client\Account\VisitController::class, 'attachment'])->name('lab-results.attachment');

    // BN-16
    Route::get('/hoa-don', [Client\Account\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/hoa-don/{invoice}', [Client\Account\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/hoa-don/{invoice}/pdf', [Client\Account\InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/hoa-don/{invoice}/thanh-toan', [Client\Account\PaymentController::class, 'invoice'])->name('invoices.pay');

    // BN-17
    Route::get('/thong-bao', [Client\Account\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/thong-bao/{id}/doc', [Client\Account\NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/thong-bao/doc-tat-ca', [Client\Account\NotificationController::class, 'readAll'])->name('notifications.read-all');
});

/*
|--------------------------------------------------------------------------
| Hệ thống quản lý nội bộ
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'staff'])->prefix('quan-ly')->name('staff.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/thong-bao', [Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/thong-bao/{id}/doc', [Admin\NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/thong-bao/doc-tat-ca', [Admin\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/tai-khoan', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/tai-khoan', [Admin\ProfileController::class, 'update'])->name('profile.update');

    // ---------------- Lễ tân ----------------
    Route::middleware('can:appointments.view')->group(function () {
        Route::get('/lich-hen', [Admin\Reception\AppointmentController::class, 'index'])->name('appointments.index');           // LT-01
        Route::get('/lich-hen/{appointment}', [Admin\Reception\AppointmentController::class, 'show'])->name('appointments.show')->whereNumber('appointment');
    });
    Route::middleware('can:appointments.manage')->group(function () {
        Route::get('/lich-hen/tao-moi', [Admin\Reception\AppointmentController::class, 'create'])->name('appointments.create');    // LT-05
        Route::post('/lich-hen', [Admin\Reception\AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('/lich-hen/{appointment}/xac-nhan', [Admin\Reception\AppointmentController::class, 'confirm'])->name('appointments.confirm'); // LT-02
        Route::post('/lich-hen/{appointment}/tu-choi', [Admin\Reception\AppointmentController::class, 'reject'])->name('appointments.reject');
        Route::post('/lich-hen/{appointment}/huy', [Admin\Reception\AppointmentController::class, 'cancel'])->name('appointments.cancel');      // LT-06
        Route::get('/lich-hen/{appointment}/doi-lich', [Admin\Reception\AppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule');
        Route::post('/lich-hen/{appointment}/doi-lich', [Admin\Reception\AppointmentController::class, 'reschedule'])->name('appointments.reschedule.store');
    });

    Route::middleware('can:reception.manage')->group(function () {
        Route::get('/check-in', [Admin\Reception\ReceptionController::class, 'checkinForm'])->name('reception.checkin');           // LT-03
        Route::post('/check-in/tim', [Admin\Reception\ReceptionController::class, 'lookup'])->name('reception.lookup');
        Route::post('/check-in/{appointment}', [Admin\Reception\ReceptionController::class, 'checkin'])->name('reception.checkin.store');
        Route::get('/tiep-nhan', [Admin\Reception\ReceptionController::class, 'walkInForm'])->name('reception.walkin');             // LT-04
        Route::post('/tiep-nhan', [Admin\Reception\ReceptionController::class, 'walkIn'])->name('reception.walkin.store');

        Route::get('/hang-doi', [Admin\Reception\QueueController::class, 'index'])->name('queue.index');                            // LT-08
        Route::post('/hang-doi/{visit}/cuoi-hang', [Admin\Reception\QueueController::class, 'moveToEnd'])->name('queue.move-to-end');
        Route::post('/hang-doi/{visit}/uu-tien', [Admin\Reception\QueueController::class, 'togglePriority'])->name('queue.priority');
        Route::post('/hang-doi/{visit}/chuyen-bac-si', [Admin\Reception\QueueController::class, 'transfer'])->name('queue.transfer');
        Route::post('/hang-doi/{visit}/huy', [Admin\Reception\QueueController::class, 'cancel'])->name('queue.cancel');
        Route::get('/luot-kham/{visit}/phieu', [Admin\Reception\QueueController::class, 'ticket'])->name('queue.ticket');          // LT-07
    });
    Route::get('/hang-doi/du-lieu', [Admin\Reception\QueueController::class, 'data'])->name('queue.data');                         // JSON cho polling

    Route::middleware('can:patients.view')->group(function () {
        Route::get('/benh-nhan', [Admin\Reception\PatientController::class, 'index'])->name('patients.index');                      // LT-10
        Route::get('/benh-nhan/tim', [Admin\Reception\PatientController::class, 'search'])->name('patients.search');                // JSON
        Route::get('/benh-nhan/{patient}', [Admin\Reception\PatientController::class, 'show'])->name('patients.show')->whereNumber('patient');
    });
    Route::middleware('can:patients.manage')->group(function () {
        Route::get('/benh-nhan/tao-moi', [Admin\Reception\PatientController::class, 'create'])->name('patients.create');
        Route::post('/benh-nhan', [Admin\Reception\PatientController::class, 'store'])->name('patients.store');
        Route::get('/benh-nhan/{patient}/sua', [Admin\Reception\PatientController::class, 'edit'])->name('patients.edit');          // LT-11
        Route::put('/benh-nhan/{patient}', [Admin\Reception\PatientController::class, 'update'])->name('patients.update');
    });

    // ---------------- Bác sĩ ----------------
    Route::middleware('can:medical.examine')->group(function () {
        Route::get('/lich-lam-viec', [Admin\Doctor\ScheduleController::class, 'index'])->name('schedule.index');                // BS-01
        Route::get('/kham-benh', [Admin\Doctor\ExamController::class, 'index'])->name('exam.index');                            // BS-03
        Route::post('/kham-benh/goi-tiep', [Admin\Doctor\ExamController::class, 'callNext'])->name('exam.call-next');
        Route::post('/kham-benh/{visit}/goi', [Admin\Doctor\ExamController::class, 'call'])->name('exam.call');
        Route::post('/kham-benh/{visit}/goi-lai', [Admin\Doctor\ExamController::class, 'recall'])->name('exam.recall');
        Route::post('/kham-benh/{visit}/bo-qua', [Admin\Doctor\ExamController::class, 'skip'])->name('exam.skip');
        Route::get('/kham-benh/{visit}', [Admin\Doctor\ExamController::class, 'show'])->name('exam.show');                      // BS-04..BS-12
        Route::put('/kham-benh/{visit}/lam-sang', [Admin\Doctor\ExamController::class, 'saveClinical'])->name('exam.clinical');  // BS-05
        Route::put('/kham-benh/{visit}/chan-doan', [Admin\Doctor\ExamController::class, 'saveDiagnoses'])->name('exam.diagnoses'); // BS-06
        Route::post('/kham-benh/{visit}/chi-dinh', [Admin\Doctor\ExamController::class, 'orderServices'])->name('exam.orders');   // BS-07
        Route::delete('/chi-dinh/{serviceOrder}', [Admin\Doctor\ExamController::class, 'cancelOrder'])->name('exam.orders.cancel');
        Route::put('/kham-benh/{visit}/don-thuoc', [Admin\Doctor\ExamController::class, 'savePrescription'])->name('exam.prescription'); // BS-09
        Route::post('/kham-benh/{visit}/hoan-thanh', [Admin\Doctor\ExamController::class, 'complete'])->name('exam.complete');   // BS-12
        Route::post('/kham-benh/{visit}/bo-sung', [Admin\Doctor\ExamController::class, 'addendum'])->name('exam.addendum');      // BR-08
        Route::get('/icd10/tim', [Admin\Doctor\ExamController::class, 'searchIcd'])->name('icd10.search');                       // JSON
        Route::get('/thuoc/tim', [Admin\Doctor\ExamController::class, 'searchMedicine'])->name('medicines.search');               // JSON
    });
    Route::middleware('can:leave.request')->group(function () {
        Route::get('/nghi-phep', [Admin\Doctor\LeaveRequestController::class, 'index'])->name('leave.index');                    // BS-02
        Route::post('/nghi-phep', [Admin\Doctor\LeaveRequestController::class, 'store'])->name('leave.store');
        Route::delete('/nghi-phep/{leaveRequest}', [Admin\Doctor\LeaveRequestController::class, 'destroy'])->name('leave.destroy');
    });
    Route::middleware('can:medical.view')->group(function () {
        Route::get('/benh-an/{visit}', [Admin\Doctor\MedicalRecordController::class, 'show'])->name('records.show');            // BS-04 xem bệnh án cũ
        Route::get('/benh-an/{visit}/in-don', [Admin\Doctor\MedicalRecordController::class, 'printPrescription'])->name('records.print-prescription'); // BS-13
        Route::get('/benh-an/{visit}/in-chi-dinh', [Admin\Doctor\MedicalRecordController::class, 'printOrders'])->name('records.print-orders');
    });
    Route::get('/ket-qua/{labResult}/tep', [Admin\Doctor\MedicalRecordController::class, 'attachment'])
        ->name('lab-results.attachment')->middleware('permission:medical.view|lab.manage');

    // ---------------- Cận lâm sàng ----------------
    Route::middleware('can:lab.manage')->group(function () {
        Route::get('/can-lam-sang', [Admin\Lab\LabController::class, 'index'])->name('lab.index');                           // CL-01
        Route::get('/can-lam-sang/{serviceOrder}', [Admin\Lab\LabController::class, 'show'])->name('lab.show');
        Route::post('/can-lam-sang/{serviceOrder}/bat-dau', [Admin\Lab\LabController::class, 'start'])->name('lab.start');     // CL-02
        Route::post('/can-lam-sang/{serviceOrder}/ket-qua', [Admin\Lab\LabController::class, 'saveResult'])->name('lab.result'); // CL-03, CL-04
    });

    // ---------------- Thu ngân ----------------
    Route::middleware('can:billing.manage')->group(function () {
        Route::get('/thu-ngan', [Admin\Cashier\CashierController::class, 'index'])->name('cashier.index');                       // TD-01
        Route::get('/thu-ngan/bao-cao-ca', [Admin\Cashier\CashierController::class, 'report'])->name('cashier.report');          // TD-12
        Route::get('/hoa-don/{invoice}', [Admin\Cashier\CashierController::class, 'show'])->name('cashier.show');
        Route::post('/hoa-don/{invoice}/thu-tien', [Admin\Cashier\CashierController::class, 'pay'])->name('cashier.pay');         // TD-02
        Route::post('/hoa-don/{invoice}/huy', [Admin\Cashier\CashierController::class, 'cancel'])->name('cashier.cancel');        // TD-04
        Route::post('/hoa-don/{invoice}/hoan-tien', [Admin\Cashier\CashierController::class, 'refund'])->name('cashier.refund');
        Route::get('/hoa-don/{invoice}/in', [Admin\Cashier\CashierController::class, 'print'])->name('cashier.print');            // TD-03
    });

    // ---------------- Dược & kho ----------------
    Route::middleware('can:pharmacy.dispense')->group(function () {
        Route::get('/cap-thuoc', [Admin\Pharmacy\PharmacyController::class, 'index'])->name('pharmacy.index');                    // TD-05
        Route::get('/cap-thuoc/{prescription}', [Admin\Pharmacy\PharmacyController::class, 'show'])->name('pharmacy.show');
        Route::post('/cap-thuoc/{prescription}', [Admin\Pharmacy\PharmacyController::class, 'dispense'])->name('pharmacy.dispense'); // TD-06
    });
    Route::middleware('can:inventory.manage')->group(function () {
        Route::resource('thuoc', Admin\Pharmacy\MedicineController::class)->parameters(['thuoc' => 'medicine'])->names('medicines')->except('show'); // TD-07
        Route::get('/nhap-kho', [Admin\Pharmacy\StockReceiptController::class, 'index'])->name('receipts.index');                 // TD-08
        Route::get('/nhap-kho/tao-moi', [Admin\Pharmacy\StockReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/nhap-kho', [Admin\Pharmacy\StockReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/nhap-kho/{receipt}', [Admin\Pharmacy\StockReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/kho', [Admin\Pharmacy\InventoryController::class, 'index'])->name('inventory.index');                        // TD-10
        Route::get('/kho/thuoc/{medicine}', [Admin\Pharmacy\InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/kho/lo/{batch}/xuat-huy', [Admin\Pharmacy\InventoryController::class, 'dispose'])->name('inventory.dispose'); // TD-09
        Route::post('/kho/lo/{batch}/kiem-ke', [Admin\Pharmacy\InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::get('/kho/bao-cao', [Admin\Pharmacy\InventoryController::class, 'report'])->name('inventory.report');              // TD-11
    });

    // ---------------- Quản trị ----------------
    Route::middleware('can:catalog.manage')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::resource('chuyen-khoa', Admin\Management\SpecialtyController::class)->parameters(['chuyen-khoa' => 'specialty'])->names('specialties')->except('show'); // QT-03
        Route::resource('phong', Admin\Management\RoomController::class)->parameters(['phong' => 'room'])->names('rooms')->except('show');                          // QT-04
        Route::resource('bac-si', Admin\Management\DoctorController::class)->parameters(['bac-si' => 'doctor'])->names('doctors')->except('show');                  // QT-05
        Route::resource('dich-vu', Admin\Management\ServiceController::class)->parameters(['dich-vu' => 'service'])->names('services')->except('show');             // QT-06
        Route::get('/icd10', [Admin\Management\Icd10Controller::class, 'index'])->name('icd10.index');                                                              // QT-07
        Route::post('/icd10', [Admin\Management\Icd10Controller::class, 'store'])->name('icd10.store');
        Route::post('/icd10/import', [Admin\Management\Icd10Controller::class, 'import'])->name('icd10.import');
        Route::delete('/icd10/{icd10Code}', [Admin\Management\Icd10Controller::class, 'destroy'])->name('icd10.destroy');
    });
    Route::middleware('can:schedule.manage')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/ca-lam-viec', [Admin\Management\WorkShiftController::class, 'index'])->name('shifts.index');                    // QT-08
        Route::post('/ca-lam-viec', [Admin\Management\WorkShiftController::class, 'store'])->name('shifts.store');
        Route::put('/ca-lam-viec/{workShift}', [Admin\Management\WorkShiftController::class, 'update'])->name('shifts.update');
        Route::delete('/ca-lam-viec/{workShift}', [Admin\Management\WorkShiftController::class, 'destroy'])->name('shifts.destroy');
        Route::post('/ca-lam-viec/sinh-slot', [Admin\Management\WorkShiftController::class, 'generate'])->name('shifts.generate');   // QT-09
        Route::get('/khung-gio', [Admin\Management\WorkShiftController::class, 'slots'])->name('slots.index');
        Route::post('/khung-gio/{timeSlot}/bat-tat', [Admin\Management\WorkShiftController::class, 'toggleSlot'])->name('slots.toggle');
        Route::get('/duyet-nghi', [Admin\Management\LeaveApprovalController::class, 'index'])->name('leaves.index');                // QT-10
        Route::get('/duyet-nghi/{leaveRequest}', [Admin\Management\LeaveApprovalController::class, 'show'])->name('leaves.show');
        Route::post('/duyet-nghi/{leaveRequest}/duyet', [Admin\Management\LeaveApprovalController::class, 'approve'])->name('leaves.approve');
        Route::post('/duyet-nghi/{leaveRequest}/tu-choi', [Admin\Management\LeaveApprovalController::class, 'reject'])->name('leaves.reject');
        Route::post('/duyet-nghi/lich/{appointment}/doi-bac-si', [Admin\Management\LeaveApprovalController::class, 'reassign'])->name('leaves.reassign');
        Route::post('/duyet-nghi/lich/{appointment}/huy', [Admin\Management\LeaveApprovalController::class, 'cancelAppointment'])->name('leaves.cancel-appointment');
    });
    Route::middleware('can:staff.manage')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::resource('nhan-vien', Admin\Management\StaffController::class)->parameters(['nhan-vien' => 'user'])->names('staff')->except('show'); // QT-01
        Route::post('/nhan-vien/{user}/khoa', [Admin\Management\StaffController::class, 'toggle'])->name('staff.toggle');
        Route::get('/phan-quyen', [Admin\Management\RoleController::class, 'index'])->name('roles.index');                         // QT-02
        Route::put('/phan-quyen', [Admin\Management\RoleController::class, 'update'])->name('roles.update');
        Route::get('/tai-khoan-benh-nhan', [Admin\Management\PatientAccountController::class, 'index'])->name('patient-accounts.index'); // QT-12
        Route::post('/tai-khoan-benh-nhan/{user}/khoa', [Admin\Management\PatientAccountController::class, 'toggle'])->name('patient-accounts.toggle');
        Route::post('/tai-khoan-benh-nhan/{user}/go-han-che', [Admin\Management\PatientAccountController::class, 'resetNoShow'])->name('patient-accounts.reset-no-show');
    });
    Route::middleware('can:settings.manage')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/cau-hinh', [Admin\Management\SettingController::class, 'edit'])->name('settings.edit');                        // QT-11
        Route::put('/cau-hinh', [Admin\Management\SettingController::class, 'update'])->name('settings.update');
    });
    Route::middleware('can:reviews.manage')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/danh-gia', [Admin\Management\ReviewController::class, 'index'])->name('reviews.index');                       // QT-13
        Route::post('/danh-gia/{review}/an-hien', [Admin\Management\ReviewController::class, 'toggle'])->name('reviews.toggle');
    });
    Route::middleware('can:reports.view')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/bao-cao', [Admin\Management\ReportController::class, 'index'])->name('reports.index');                        // QT-15
        Route::get('/bao-cao/xuat', [Admin\Management\ReportController::class, 'export'])->name('reports.export');                  // QT-16
    });
    Route::middleware('can:audit.view')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/nhat-ky', [Admin\Management\AuditLogController::class, 'index'])->name('audit.index');                         // QT-17
    });
});
