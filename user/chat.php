<?php
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id <= 0) {
    die("رقم الطلب غير صحيح.");
}

// جلب تفاصيل الطلب والتأكد من الصلاحية
$sql_req = "SELECT r.*, 
            CONCAT(u1.first_name, ' ', u1.last_name) as requester_name, 
            CONCAT(u2.first_name, ' ', u2.last_name) as provider_name, 
            s.name as service_name
            FROM requests r
            JOIN users u1 ON r.requester_id = u1.user_id
            JOIN users u2 ON r.provider_id = u2.user_id
            JOIN services s ON r.service_id = s.service_id
            WHERE r.request_id = ?";
$stmt = $conn->prepare($sql_req);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("الطلب غير موجود.");
}

$req = $res->fetch_assoc();
$stmt->close();

if ($user_id != $req['requester_id'] && $user_id != $req['provider_id']) {
    die("ليس لديك صلاحية للدخول إلى هذه المحادثة.");
}

$is_completed = ($req['status'] === 'completed');
$other_party_name = ($user_id == $req['requester_id']) ? $req['provider_name'] : $req['requester_name'];
$other_party_img_src = "../assets/images/default-avatar.png"; // لا يوجد صور في قاعدة البيانات الحالية

$page_title = 'الدردشة مع ' . $other_party_name;
$page_css = '
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* تحسينات التصميم الأساسي */
        body { background-color: #f0f2f5; overflow: hidden; }
        .chat-container { max-width: 1300px; width: 95%; margin: 130px auto 30px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-direction: column; height: calc(100vh - 160px); border: 1px solid #e2e8f0; }
        .chat-header { background-color: #021C7B; color: #fff; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10; }
        .chat-header .user-info { display: flex; align-items: center; gap: 15px; }
        .chat-header h5 { margin: 0; font-weight: 700; font-size: 1.15rem; letter-spacing: 0.5px; }
        .chat-header .back-btn { color: #fff; font-size: 1.3rem; text-decoration: none; transition: 0.2s; }
        .chat-header .back-btn:hover { transform: translateX(3px); }
        .chat-messages { flex-grow: 1; padding: 25px; overflow-y: auto; background-color: #efeae2; display: flex; flex-direction: column; gap: 15px; background-image: url("https://www.transparenttextures.com/patterns/cubes.png"); }
        .message-box { max-width: 75%; padding: 12px 18px; border-radius: 16px; position: relative; font-size: 0.95rem; line-height: 1.6; box-shadow: 0 1px 2px rgba(0,0,0,0.1); word-wrap: break-word; }
        .message-mine { background-color: #dcf8c6; align-self: flex-end; border-top-left-radius: 4px; }
        .message-other { background-color: #ffffff; align-self: flex-start; border-top-right-radius: 4px; }
        .message-time { font-size: 0.75rem; color: #64748b; text-align: right; margin-top: 6px; display: block; font-weight: 600; }
        .chat-input-area { padding: 15px 25px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; z-index: 10; }
        .chat-input-area input { flex-grow: 1; border: 1px solid #cbd5e1; background-color: #fff; padding: 14px 22px; border-radius: 30px; outline: none; font-size: 0.95rem; transition: border-color 0.3s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        .chat-input-area input:focus { border-color: #021C7B; }
        .chat-input-area button { background-color: #021C7B; color: #fff; border: none; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(2,28,123,0.2); }
        .chat-input-area button:hover { background-color: #104496; transform: scale(1.05); }
        .chat-input-area button:disabled { background-color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }
        .chat-closed-notice { text-align: center; padding: 15px; background-color: #fef2f2; color: #991b1b; font-weight: bold; font-size: 0.9rem; border-bottom: 1px solid #fecaca; }
        
        /* توافق الهاتف */
        @media (max-width: 992px) { 
            body { background-color: #efeae2; overflow: hidden; }
            .chat-container {
                position: fixed;
                top: 70px; 
                bottom: 0; 
                left: 0; 
                right: 0;
                height: calc(100vh - 70px);
                height: calc(100dvh - 70px);
                margin: 0; 
                width: 100vw;
                border-radius: 0;
                border: none;
                z-index: 100;
                display: flex;
                flex-direction: column;
            }
            .chat-messages { padding: 10px; gap: 10px; flex: 1 1 auto; overflow-y: auto; }
            .chat-input-area { padding: 10px 15px; flex-shrink: 0; }
            .chat-input-area button { width: 45px; height: 45px; font-size: 1.1rem; flex-shrink: 0; }
            .message-box { max-width: 90%; padding: 10px 15px; }
            .chat-header { padding: 12px 15px; flex-shrink: 0; }
        }
        /* الوضع الليلي (Dark Mode) */
        body.dark-mode { background-color: #0f172a; overflow: hidden; }
        body.dark-mode .chat-container { background: #1e293b; border-color: #334155; }
        body.dark-mode .chat-header { background-color: #0f172a; border-bottom: 1px solid #1e293b; }
        body.dark-mode .message-mine { background-color: #047857; color: #f8fafc; }
        body.dark-mode .message-other { background-color: #1e293b; color: #f8fafc; border: 1px solid #334155; }
        body.dark-mode .message-time { color: #94a3b8; }
        body.dark-mode .chat-input-area { background-color: #0f172a; border-color: #1e293b; }
        body.dark-mode .chat-input-area input { background-color: #1e293b; color: #f8fafc; border-color: #334155; }
        body.dark-mode .chat-input-area input::placeholder { color: #64748b; }
        body.dark-mode .chat-input-area input:focus { border-color: #38bdf8; }
        body.dark-mode .chat-input-area button { background-color: #38bdf8; color: #0f172a; }
        body.dark-mode .chat-input-area button:hover { background-color: #0ea5e9; }
        body.dark-mode .chat-closed-notice { background-color: #450a0a; color: #fca5a5; border-color: #7f1d1d; }
    </style>
';
include '../includes/user_header.php'; 
include '../includes/user_navbar.php'; 
?>

<div class="container-fluid p-0 p-md-3">
    <div class="chat-container">
        <!-- Header -->
        <div class="chat-header">
            <div class="user-info">
                <a href="requests.php" class="back-btn ms-2"><i class="fa-solid fa-arrow-right"></i></a>
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($other_party_name); ?></h5>
                    <small id="typingIndicator" style="display:none; color: #d1d5db; font-size: 0.8rem; font-style: italic;">يكتب الآن...</small>
                </div>
            </div>
            <i class="fa-solid fa-comments fs-4"></i>
        </div>

        <?php if ($is_completed): ?>
            <div class="chat-closed-notice">
                هذا الطلب مكتمل. المحادثة مغلقة للقراءة فقط، وسيتم حذفها تلقائياً
            </div>
        <?php endif; ?>

        <!-- Messages Area -->
        <div class="chat-messages" id="chatBox">
            <!-- سيتم تعبئة الرسائل هنا عبر جافاسكربت -->
        </div>

        <!-- Quick Replies -->
        <?php if(!$is_completed): ?>
        <div id="quickRepliesArea" style="display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; background: #fff; border-top: 1px solid #ddd;">
            <button class="btn btn-sm btn-outline-primary rounded-pill m-0" onclick="useQuickReply('مرحباً، يسعدني العمل معك')">مرحباً، يسعدني العمل معك</button>
            <button class="btn btn-sm btn-outline-primary rounded-pill m-0" onclick="useQuickReply('الرجاء إرسال تفاصيل أكثر')">الرجاء إرسال تفاصيل أكثر</button>
            <button class="btn btn-sm btn-outline-primary rounded-pill m-0" onclick="useQuickReply('تم استلام الطلب')">تم استلام الطلب</button>
            <button class="btn btn-sm btn-outline-primary rounded-pill m-0" onclick="useQuickReply('شكراً لك')">شكراً لك</button>
        </div>
        <?php endif; ?>

        <!-- Input Area -->
        <div class="chat-input-area" <?php if($is_completed) echo 'style="display:none;"'; ?>>
            <label for="attachFile" style="cursor: pointer; margin-left: 10px; color: #6c757d; font-size: 1.2rem;">
                <i class="fa-solid fa-paperclip"></i>
            </label>
            <input type="file" id="attachFile" style="display: none;" accept="image/*,.pdf,.doc,.docx" onchange="previewAttachment()">
            <input type="text" id="msgInput" placeholder="اكتب رسالتك هنا..." autocomplete="off">
            <button id="sendBtn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
        <div id="attachmentPreview" style="display: none; padding: 10px; background: #f8f9fa; border-top: 1px solid #ddd; text-align: right;">
            <span id="attachName" style="font-size: 0.9rem; color: #333; margin-left: 10px;"></span>
            <button onclick="clearAttachment()" style="background: none; border: none; color: #dc3545;"><i class="fa-solid fa-times"></i></button>
        </div>
    </div>
</div>

<script>
let lastMsgId = 0;
const reqId = <?php echo $request_id; ?>;
const csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const chatBox = document.getElementById("chatBox");

function scrollToBottom() {
    chatBox.scrollTop = chatBox.scrollHeight;
}

function appendMessage(msg) {
    const div = document.createElement("div");
    div.className = "message-box " + (msg.is_mine ? "message-mine" : "message-other");
    
    // 1. المرفقات (مع التحقق من بادئة المسار)
    if (msg.attachment && typeof msg.attachment === 'string') {
        const cleanAttachment = msg.attachment.replace(/\\/g, '/');
        if (cleanAttachment.startsWith('uploads/chat/')) {
            const ext = cleanAttachment.split('.').pop().toLowerCase();
            const attachWrapper = document.createElement("div");
            attachWrapper.className = "mb-2";
            const a = document.createElement("a");
            a.href = '../' + encodeURI(cleanAttachment);
            a.target = "_blank";
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                const img = document.createElement("img");
                img.src = '../' + encodeURI(cleanAttachment);
                img.style.maxWidth = "200px";
                img.style.borderRadius = "8px";
                img.alt = "مرفق";
                a.appendChild(img);
            } else {
                a.className = "btn btn-sm btn-light";
                a.innerHTML = '<i class="fa-solid fa-file me-1"></i> تحميل المرفق';
            }
            attachWrapper.appendChild(a);
            div.appendChild(attachWrapper);
        }
    }

    // 2. نص الرسالة عبر textContent لمنع أي XSS تماماً
    const textDiv = document.createElement("div");
    textDiv.className = "message-text " + (msg.is_hidden == 1 ? "text-muted fst-italic" : "");
    textDiv.textContent = msg.message_text || '';
    div.appendChild(textDiv);

    // 3. الوقت وعلامات القراءة وزر البلاغ
    const timeSpan = document.createElement("span");
    timeSpan.className = "message-time";
    timeSpan.textContent = (msg.formatted_time || '') + ' ';

    if (msg.is_mine) {
        const tick = document.createElement("i");
        tick.className = `fa-solid fa-check-double ms-1 read-tick ${msg.is_read == 1 ? 'text-primary' : 'text-muted'}`;
        tick.setAttribute('data-msg-id', msg.message_id);
        tick.style.fontSize = "0.75rem";
        timeSpan.appendChild(tick);
    }

    if (!msg.is_mine && msg.is_hidden != 1) {
        const dropdown = document.createElement("div");
        dropdown.className = "dropdown d-inline-block ms-2";
        dropdown.innerHTML = `
            <i class="fa-solid fa-ellipsis-vertical text-muted" data-bs-toggle="dropdown" style="cursor:pointer; padding: 0 5px;" title="خيارات"></i>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 120px; border-radius: 8px; font-size: 0.85rem; padding: 5px 0;">
                <li><a class="dropdown-item text-danger" href="#" onclick="reportMessage(${parseInt(msg.message_id)}); return false;"><i class="fa-solid fa-triangle-exclamation me-1"></i> إبلاغ</a></li>
            </ul>
        `;
        timeSpan.appendChild(dropdown);
    }

    div.appendChild(timeSpan);
    chatBox.appendChild(div);

    // إخفاء الردود السريعة بمجرد ظهور أي رسالة في المحادثة
    const quickRepliesArea = document.getElementById("quickRepliesArea");
    if (quickRepliesArea) {
        quickRepliesArea.style.display = 'none';
    }
}

function reportMessage(msgId) {
    Swal.fire({
        title: 'إبلاغ عن الرسالة',
        html: `
            <p style="font-size: 0.95rem; margin-bottom: 15px;">يرجى تحديد سبب الإبلاغ عن هذه الرسالة:</p>
            <select id="reportReason" class="form-select" style="font-size: 0.95rem;">
                <option value="رسالة سبام مزعجة">رسالة سبام مزعجة</option>
                <option value="تحتوي على شتائم أو إهانة">تحتوي على شتائم أو إهانة</option>
                <option value="احتيال أو نصب">احتيال أو نصب</option>
                <option value="أخرى">أخرى</option>
            </select>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'إرسال البلاغ',
        cancelButtonText: 'إلغاء',
        preConfirm: () => {
            return document.getElementById('reportReason').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const reason = result.value;
            const formData = new FormData();
            formData.append('action', 'report_message');
            formData.append('csrf_token', csrfToken);
            formData.append('request_id', reqId);
            formData.append('message_id', msgId);
            formData.append('reason', reason);

            fetch('../php/report_message.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('تم الإبلاغ', 'تم إرسال البلاغ للإدارة بنجاح. شكراً لك.', 'success');
                } else {
                    Swal.fire('خطأ', data.message || 'حدث خطأ أثناء الإبلاغ.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('خطأ', 'تعذر الاتصال بالخادم.', 'error');
            });
        }
    });
}

function fetchMessages() {
    fetch(`../php/fetch_messages.php?request_id=${reqId}&last_msg_id=${lastMsgId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        lastMsgId = msg.message_id;
                    });
                    scrollToBottom();
                }
                
                // تحديث مؤشرات القراءة
                if (data.last_read_id) {
                    document.querySelectorAll('.read-tick').forEach(tick => {
                        if (parseInt(tick.getAttribute('data-msg-id')) <= parseInt(data.last_read_id)) {
                            tick.classList.remove('text-muted');
                            tick.classList.add('text-primary');
                        }
                    });
                }
                
                // تحديث مؤشر الكتابة
                const typingInd = document.getElementById('typingIndicator');
                if (data.is_typing) {
                    typingInd.style.display = 'block';
                } else {
                    typingInd.style.display = 'none';
                }
            }
        })
        .catch(err => console.error(err));
}

let lastTypingTime = 0;
document.getElementById("msgInput").addEventListener("input", function() {
    const now = Date.now();
    if (now - lastTypingTime > 2500) { // إرسال طلب كل ثانيتين ونصف كحد أقصى أثناء الكتابة المستمرة
        lastTypingTime = now;
        const formData = new FormData();
        formData.append('request_id', reqId);
        formData.append('csrf_token', csrfToken);
        fetch('../php/typing_status.php', { method: 'POST', body: formData });
    }
});

function previewAttachment() {
    const fileInput = document.getElementById("attachFile");
    const preview = document.getElementById("attachmentPreview");
    const attachName = document.getElementById("attachName");
    
    if (fileInput.files.length > 0) {
        attachName.innerText = fileInput.files[0].name;
        preview.style.display = "block";
    }
}

function clearAttachment() {
    document.getElementById("attachFile").value = "";
    document.getElementById("attachmentPreview").style.display = "none";
}

function useQuickReply(text) {
    document.getElementById("msgInput").value = text;
    sendMessage();
}

function sendMessage() {
    const input = document.getElementById("msgInput");
    const text = input.value.trim();
    const fileInput = document.getElementById("attachFile");
    
    if (text === "" && fileInput.files.length === 0) return;

    document.getElementById("sendBtn").disabled = true;

    const formData = new FormData();
    formData.append("csrf_token", csrfToken);
    formData.append("request_id", reqId);
    formData.append("message_text", text);
    
    if (fileInput.files.length > 0) {
        formData.append("attachment", fileInput.files[0]);
    }

    fetch("../php/send_message.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById("sendBtn").disabled = false;
        if (data.status === "success") {
            input.value = "";
            clearAttachment();
            fetchMessages();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: data.message,
                confirmButtonColor: '#021C7B'
            });
        }
    })
    .catch(err => {
        console.error(err);
        document.getElementById("sendBtn").disabled = false;
    });
}

document.getElementById("msgInput").addEventListener("keypress", function(e) {
    if (e.key === "Enter") sendMessage();
});

// الجلب الأولي ثم بشكل دوري كل 2 ثانية
fetchMessages();
setInterval(fetchMessages, 2000);

</script>

<!-- تضمين Bootstrap JS لعمل القوائم المنسدلة -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

