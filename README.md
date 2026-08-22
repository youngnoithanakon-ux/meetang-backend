# มีตังค์ (MeeTang) - Backend API 💰

ระบบ Backend สำหรับแอปพลิเคชัน "มีตังค์" (MeeTang) พัฒนาด้วย **Laravel 11** ทำหน้าที่จัดการข้อมูลกระเป๋าเงิน, การเงินเข้า-ออก, เป้าหมายการออม, และรายการประจำ (Recurring Transactions)

## 🚀 ฟีเจอร์หลัก (Features)
- 🔐 **Authentication:** ระบบ Login / Register และจัดการ Token ด้วย Laravel Sanctum
- 💼 **Wallets & Goals:** จัดการกระเป๋าเงิน และเป้าหมายการออมเงิน (Savings Goal)
- 💸 **Transactions:** ระบบบันทึกรายรับ รายจ่าย และโอนเงินระหว่างกระเป๋า
- 🔁 **Recurring Transactions:** ระบบตั้งเวลาหักเงินหรือโอนเงินอัตโนมัติรายเดือน
- 📊 **Budgets & Charts:** ระบบดึงข้อมูลเพื่อสรุปงบประมาณและกราฟสถิติ
- 🖼️ **File Uploads:** รองรับการอัปโหลดไฟล์สลิป/รูปภาพประกอบรายการ

## 🛠️ Tech Stack
- **Framework:** Laravel 11
- **Database:** SQLite (ค่าเริ่มต้น) / MySQL / PostgreSQL
- **Authentication:** Laravel Sanctum

## 📥 การติดตั้ง (Installation)

1. **Clone โปรเจกต์**
   ```bash
   git clone https://github.com/youngnoithanakon-ux/meetang-backend.git
   cd meetang-backend
   ```

2. **ติดตั้ง Dependencies**
   ```bash
   composer install
   ```

3. **ตั้งค่า Environment**
   คัดลอกไฟล์ `.env.example` เป็น `.env`
   ```bash
   cp .env.example .env
   ```

4. **สร้าง App Key**
   ```bash
   php artisan key:generate
   ```

5. **ตั้งค่า Database & Migrate**
   ระบบใช้ SQLite เป็นค่าเริ่มต้น สามารถรันคำสั่งนี้เพื่อสร้างตารางได้เลย
   ```bash
   php artisan migrate
   ```
   *(หมายเหตุ: เพื่อให้ Mobile App แนบรูปได้สมบูรณ์ใน Localhost อาจต้องปรับแต่ง Storage Link)*
   ```bash
   php artisan storage:link
   ```

6. **รันเซิร์ฟเวอร์**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   *(หมายเหตุ: รันด้วย `--host=0.0.0.0` เพื่อให้ Mobile App บนโทรศัพท์จริง หรือ Emulator สามารถเชื่อมต่อผ่านวง LAN เดียวกันได้)*

## 📡 API Endpoints พื้นฐาน

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/api/register` | สมัครสมาชิก |
| POST   | `/api/login` | เข้าสู่ระบบ |
| GET    | `/api/wallets` | ดึงข้อมูลกระเป๋าเงินทั้งหมด |
| POST   | `/api/wallets` | สร้างกระเป๋าเงิน / เป้าหมายการออม |
| GET    | `/api/transactions` | ดึงข้อมูลประวัติการเงิน |
| POST   | `/api/transactions` | สร้างรายการ (รับ/จ่าย/โอน) พร้อมรูปภาพ |
| POST   | `/api/recurring-transactions`| ตั้งเวลารายการประจำ |
| POST   | `/api/process-recurrings` | คำนวณและหักเงินรายการประจำเดือน |

## 🔒 Security
API ทั้งหมด (ยกเว้น Login/Register) จำเป็นต้องส่ง Header `Authorization: Bearer <token>` ทุกครั้ง
