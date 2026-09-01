# 🌟 منصة إثراء (Ethraa Platform) - لنظام تبادل الخدمات والمهارات

<p align="center">
  <img src="assets/images/logo.png" alt="Ethraa Logo" width="120">
</p>

<p align="center">
  <strong>منصة أردنية رائدة ومبتكرة تتيح للمستخدمين تبادل الخدمات والخبرات التخصصية بنظام النقاط التبادلي دون الحاجة للدفع النقدي.</strong>
</p>

---

## 📌 نبذة عن المشروع (Project Overview)

منصة **إثراء (Ethraa)** هي تطبيق ويب متكامل مبني بلغة **PHP** وقواعد بيانات **MySQL**، يهدف إلى تمكين الأفراد والمهنيين من تقديم خدماتهم في مجالات متعددة (تقنية، تعليمية، مهنية، صحية، قانونية، ومجتمعية) والاستفادة في المقابل من خدمات الآخرين عبر نظام تبادل ذكي يعتمد على الرصيد الساعي / النقاط.

---

## ✨ أبرز الميزات والوظائف (Key Features)

### 👤 بوابة المستخدم (User Experience)
- **تسجيل حساب وتسجيل دخول آمن:** متوافق مع معايير الأمان مع فحص السن (18+) وأرقام الهواتف الأردنية.
- **تذكرني الذكي (Remember Me):** تشفير التوكن عبر `password_hash` مع تدوير التوكنات (`Token Rotation`).
- **محرك بحث وتصفية فوري (Live Instant Search):** تصفية الخدمات والمهن في الوقت الفعلي بتقنية Debounced Fetch.
- **حجز المواعيد وإدارة الطلبات:** نظام جدولة مواعيد دقيق يمنع الحجز المزدوج (`Double-Booking Protection`).
- **محادثات فورية مباشرة (Live Chat):** محادثة آمنة بين طالب الخدمة ومقدمها مع دعم رفع الصور وفلترة الكلمات المحظورة (`AI Content Moderation`).
- **نظام التقييمات والمراجعات:** تقييم جودة الخدمة والموثوقية بعد اكتمال الطلب.
- **المحفظة وسجل الحركات:** متابعة الرصيد ونظام النقاط والإحالات مع سجل كامل للحركات المالية (`Wallet History`).
- **الوضع الليلي المتكامل (OLED Dark Mode):** تجربة تصفح ليلية فاخرة ومريحة للعين مع حفظ التفضيلات.

### 🛡️ لوحة التحكم والإدارة (Admin Dashboard)
- **نظام التحكم في الوصول القائم على الأدوار (RBAC):** توزيع الصلاحيات بدقة بين المشرفين (`hasPermission`).
- **سجل نشاطات المشرفين (Audit Logs):** تتبع وتوثيق كافة العمليات الإدارية لضمان المساءلة والشفافية.
- **إدارة البلاغات والطعون (Reports & Appeals):** مراجعة الشكاوى والطعون واتخاذ الإجراءات التأديبية.
- **إدارة الخدمات والتصنيفات:** إضافة وتعديل المجالات والمهن والأيقونات.
- **التحكم بالمنصة وإعدادات SMTP:** تخصيص وضع الصيانة، روابط التواصل، خادم البريد، ونصوص الفوتر.

---

## 🔒 البنية الأمنية (Application Security Architecture)

تم فحص وتأمين المنصة بالكامل ضد ثغرات **OWASP Top 10**:
- **SQL Injection:** استخدام الاستعلامات المجهزة (`Prepared Statements`) في 100% من استعلامات النظام.
- **Cross-Site Scripting (XSS):** تعقيم المخرجات عبر `htmlspecialchars` وتطبيق `Content-Security-Policy`.
- **CSRF Protection:** استخدام رموز التحقق `csrf_token` المشفرة في كافة نماذج الـ POST.
- **Session Management:** إبطال الجلسات القديمة فورياً عبر `session_version` عند تغيير كلمة المرور وفحص الـ Timeout.
- **File Upload Security:** فحص الامتدادات ونوع MIME الحقيقي عبر `finfo_file`، ومنع تشغيل السكربتات بملف `.htaccess` داخلي.
- **Hardened Headers:** تفعيل `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, و `SameSite=Lax`.

---

## 🛠️ التقنيات المستخدمة (Tech Stack)

- **Backend:** PHP 8+ (Pure Vanilla / MVC-structured procedural)
- **Database:** MySQL / MariaDB (MySQLi with Prepared Statements)
- **Frontend:** HTML5, CSS3 (Custom Design + Animations), JavaScript (ES6+)
- **UI Frameworks & Libraries:** Bootstrap 5, FontAwesome 6, SweetAlert2
- **Mailing Engine:** PHPMailer (SMTP over SSL/TLS)

---

## 🚀 متطلبات وتشغيل المشروع محلياً (Installation & Setup)

1. **استنساخ المستودع:**
   ```bash
   git clone https://github.com/HussenAlNajdawi/Ethraa-Platform.git
   ```
2. **نقل المشروع إلى خادم الويب المحلي:**
   - انقل مجلد المشروع إلى مسار الخادم المحلي (مثل `c:/xampp/htdocs/Ethraa` أو `/var/www/html/Ethraa`).
3. **استيراد قاعدة البيانات:**
   - افتح `phpMyAdmin` أو سطر أوامر MySQL.
   - أنشئ قاعدة بيانات باسم `ethraa_db`.
   - استورد ملف الهيكل من: `database/schema.sql`.
4. **تهيئة إعدادات الاتصال والبريد:**
   - انسخ ملف `config/mail_config.example.php` إلى `config/mail_config.php` وضع إعدادات SMTP الخاصة بك.
   - تأكد من بيانات الاتصال في `config/db_connect.php`.
5. **تشغيل المشروع:**
   - افتح المتصفح على: `http://localhost/Ethraa/`

---

## 👨‍💻 المطور (Author)

- **حسين النجدوي (Hussen Al-Najdawi)**
- **GitHub:** [@HussenAlNajdawi](https://github.com/HussenAlNajdawi)
