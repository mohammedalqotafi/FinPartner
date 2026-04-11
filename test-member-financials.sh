#!/bin/bash

echo "================================"
echo "اختبار نظام التفاصيل المالية للعضو"
echo "================================"
echo ""

# Test 1: Check if Laravel is running
echo "1️⃣ اختبار Backend API..."
response=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/api/members/1/financials 2>/dev/null)

if [ "$response" = "200" ]; then
    echo "✅ Backend API يعمل بشكل صحيح (Status: 200)"
    echo ""
    echo "📊 عينة من البيانات:"
    curl -s http://127.0.0.1:8000/api/members/1/financials | head -n 20
    echo ""
elif [ "$response" = "000" ]; then
    echo "❌ Backend غير مشغّل!"
    echo "   قم بتشغيله: cd backend && php artisan serve"
else
    echo "⚠️  Backend يعمل لكن هناك خطأ (Status: $response)"
fi

echo ""
echo "================================"

# Test 2: Check Backend tests
echo "2️⃣ اختبار Backend Tests..."
cd backend
php artisan test --filter=MemberFinancialsTest --quiet
if [ $? -eq 0 ]; then
    echo "✅ جميع اختبارات Backend تعمل"
else
    echo "❌ بعض اختبارات Backend فشلت"
fi
cd ..

echo ""
echo "================================"

# Test 3: Check if Frontend dev server is running
echo "3️⃣ اختبار Frontend Dev Server..."
frontend_response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:5173 2>/dev/null)

if [ "$frontend_response" = "200" ]; then
    echo "✅ Frontend Dev Server يعمل"
    echo "   افتح: http://localhost:5173/members"
elif [ "$frontend_response" = "000" ]; then
    echo "❌ Frontend Dev Server غير مشغّل!"
    echo "   قم بتشغيله: cd frontend && npm run dev"
else
    echo "⚠️  Frontend يعمل لكن هناك خطأ (Status: $frontend_response)"
fi

echo ""
echo "================================"
echo "📝 الخلاصة:"
echo "================================"
echo ""
echo "للتشغيل الكامل، تحتاج إلى:"
echo "1. Terminal 1: cd backend && php artisan serve"
echo "2. Terminal 2: cd frontend && npm run dev"
echo "3. افتح المتصفح: http://localhost:5173/members"
echo "4. اضغط على أي عضو، ثم اضغط على أي بطاقة"
echo ""
