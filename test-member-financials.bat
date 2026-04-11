@echo off
chcp 65001 >nul
echo ================================
echo اختبار نظام التفاصيل المالية للعضو
echo ================================
echo.

REM Test 1: Check Backend
echo 1️⃣ اختبار Backend API...
curl -s -o nul -w "%%{http_code}" http://127.0.0.1:8000/api/members/1/financials > temp_status.txt 2>nul
set /p status=<temp_status.txt
del temp_status.txt

if "%status%"=="200" (
    echo ✅ Backend API يعمل بشكل صحيح ^(Status: 200^)
    echo.
    echo 📊 عينة من البيانات:
    curl -s http://127.0.0.1:8000/api/members/1/financials 2>nul | findstr /C:"member" /C:"deposits" /C:"withdrawals"
    echo.
) else if "%status%"=="" (
    echo ❌ Backend غير مشغّل!
    echo    قم بتشغيله: cd backend ^&^& php artisan serve
) else (
    echo ⚠️  Backend يعمل لكن هناك خطأ ^(Status: %status%^)
)

echo.
echo ================================

REM Test 2: Check Backend Tests
echo 2️⃣ اختبار Backend Tests...
cd backend
php artisan test --filter=MemberFinancialsTest --quiet
if %errorlevel%==0 (
    echo ✅ جميع اختبارات Backend تعمل
) else (
    echo ❌ بعض اختبارات Backend فشلت
)
cd ..

echo.
echo ================================

REM Test 3: Check Frontend
echo 3️⃣ اختبار Frontend Dev Server...
curl -s -o nul -w "%%{http_code}" http://localhost:5173 > temp_frontend.txt 2>nul
set /p frontend_status=<temp_frontend.txt
del temp_frontend.txt

if "%frontend_status%"=="200" (
    echo ✅ Frontend Dev Server يعمل
    echo    افتح: http://localhost:5173/members
) else if "%frontend_status%"=="" (
    echo ❌ Frontend Dev Server غير مشغّل!
    echo    قم بتشغيله: cd frontend ^&^& npm run dev
) else (
    echo ⚠️  Frontend يعمل لكن هناك خطأ ^(Status: %frontend_status%^)
)

echo.
echo ================================
echo 📝 الخلاصة:
echo ================================
echo.
echo للتشغيل الكامل، تحتاج إلى:
echo 1. Terminal 1: cd backend ^&^& php artisan serve
echo 2. Terminal 2: cd frontend ^&^& npm run dev
echo 3. افتح المتصفح: http://localhost:5173/members
echo 4. اضغط على أي عضو، ثم اضغط على أي بطاقة
echo.
pause
