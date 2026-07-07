# طلب 9: محاكاة 100 مستخدم (JMeter + Grafana)

## شو هاد الملف؟
`100-users-load-test.jmx` هو ملف JMeter (Test Plan خارجي، منفصل تماماً عن مشروع Laravel) بيحاكي **100 مستخدم متزامن**، كل واحد بينفّذ 5 جولات (Loops) عبيضربوا فيها **كل العمليات** الموجودة بالمشروع بالترتيب:

1. تسجيل دخول (`POST /api/login`) → استخراج التوكن أوتوماتيكياً
2. لائحة المنتجات (`GET /api/products`) → يختبر كاش `active_products_list`
3. منتج مفرد (`GET /api/products/{id}`) → يختبر كاش `product_{id}`
4. شراء Pessimistic (`POST /api/purchase/pessimistic`) → يضرب نفس المنتج بـ 100 ثريد بنفس اللحظة، يختبر `lockForUpdate`
5. شراء Optimistic (`POST /api/purchase/optimistic`) → لمقارنة الأداء مع الأعلى تحت نفس الحمل
6. توليد تقرير المبيعات (`GET /api/reports/sales`) → يختبر الـ Distributed Lock تبع `DailySalesBatchJob`
7. قراءة نتيجة التقرير (`GET /api/reports/download/{date}`) → يختبر كاش `sales_report_{date}`
8. Load Balancer (`GET /load-balancer/products`) → يختبر الـ Distributed Lock تبع pointer الـ Round Robin

## قبل ما تشغّلوا الاختبار

1. **سيدوا مستخدم اختباري** برصيد كافي (مثلاً 1,000,000) وعدّلوا `EMAIL`/`PASSWORD` بالـ Test Plan (User Defined Variables) ليطابقوه.
2. **سيدوا منتج** (`PRODUCT_ID=1` افتراضياً) بمخزون كبير لو بدكن تقيسوا الأداء البحت بدون فشل (مثلاً `stock = 100000`). إذا تركتوا المخزون منخفض، الاختبار رح يطلع أخطاء "المخزون غير كاف" بطلبات كتير — وهاد بالحقيقة **مطلوب ومتوقع**: لازم يثبت إنو ما في Overselling حتى تحت ضغط 100 مستخدم بنفس اللحظة (تماماً مثال Concert Seat Booking بـ Session 7).
3. شغّلوا سيرفر Laravel: `php artisan serve --port=8000` (أو حسب البورت يلي حطيتوه بـ Test Plan).
4. تأكدوا `CACHE_DRIVER=redis` بـ `.env` حتى الـ Distributed Locks (طلب 7) تشتغل فعلياً.

## تشغيل الاختبار (Command Line - أدق من الـ GUI للقياس الحقيقي)

```bash
jmeter -n -t 100-users-load-test.jmx -l results.jtl -e -o ./html-report
```

- `-n` : Non-GUI mode (أدق وأخف على الـ CPU، الـ GUI بنفسه بياخد موارد وبيأثر عالأرقام)
- `-l results.jtl` : ملف نتائج خام
- `-e -o ./html-report` : يولّد تقرير HTML تلقائي فيه Throughput/Latency/Error % (نفس الأعمدة الثلاثة تبع Session 8)

افتحوا `./html-report/index.html` وبتلاقوا فيه فعلياً نفس مفاهيم Session 8: **Throughput, Latency (P90/P95/P99), Error Rate**.

## ربط Grafana (Dashboard حي وقت التشغيل)

```bash
cd load-test
docker-compose up -d
```

هاد رح يشغّل:
- **InfluxDB** على `localhost:8086` (قاعدة بيانات `jmeter` جاهزة)
- **Grafana** على `localhost:3000` (admin / admin)

بعدين:
1. بملف الـ Test Plan، فعّلوا عنصر **"InfluxDB Backend Listener (for Grafana)"** (غيّروا `enabled="false"` لـ `enabled="true"`، أو من GUI كبسة يمين → Toggle).
2. بـ Grafana: Connections → Data Sources → Add → InfluxDB → URL: `http://localhost:8086` → Database: `jmeter` → Save & Test.
3. استوردوا Dashboard جاهز خاص بـ JMeter: Dashboards → Import → ID **5496** (JMeter Load Test Dashboard, متوفر على grafana.com).
4. شغّلوا الاختبار، وبتشوفوا الأرقام عم تتحدث Live بالـ Dashboard وقت التنفيذ.

## ملاحظة مهمة لطلب 10
نتائج هاد الاختبار (`html-report` أو Aggregate Report) هي بالضبط الأرقام يلي رح نستخدمها لطلب 10: نحدد العملية الأكثر اختناقاً (الأعلى Latency/الأكثر Error Rate)، نطبق التحسين المناسب عليها، ونعيد تشغيل نفس الاختبار للمقارنة قبل/بعد رقمياً.
