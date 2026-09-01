<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وضع الصيانة - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maintenance-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .icon-container {
            font-size: 4rem;
            color: #021C7B;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="icon-container">
            <i class="fa-solid fa-person-digging"></i>
        </div>
        <h2 class="fw-bold mb-3" style="color: #021C7B;">الموقع تحت التحديث</h2>
        <p class="text-muted mb-4">نعمل حالياً على إضافة ميزات جديدة وتحسين منصة إثراء لتقديم تجربة أفضل لكم. سنعود قريباً!</p>
        <button onclick="location.reload()" class="btn btn-primary rounded-pill px-4">تحديث الصفحة</button>
    </div>
</body>
</html>
