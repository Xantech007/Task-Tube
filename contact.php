<?php
// contact.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Tube - Contact Us</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6e44ff, #b5179e);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 20px 80px;
        }

        .contact-container {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 800px;
            width: 100%;
            text-align: center;
        }

        .contact-container h1 {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .contact-container h2 {
            font-size: 24px;
            font-weight: 500;
            color: #ff69b4;
            margin: 30px 0 15px;
        }

        .contact-container p {
            font-size: 16px;
            color: #666;
            line-height: 1.8;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-container ul {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-container ul li {
            font-size: 16px;
            color: #666;
            line-height: 1.8;
            margin-bottom: 10px;
            position: relative;
            padding-left: 25px;
        }

        .contact-container ul li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #6e44ff;
            position: absolute;
            left: 0;
            top: 2px;
        }

        .contact-info p strong {
            color: #333;
            font-weight: 600;
        }

        .contact-info p a {
            color: #6e44ff;
            text-decoration: none;
        }

        .contact-info p a:hover {
            text-decoration: underline;
        }

        .cta {
            background-color: #f0e9ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .cta p {
            color: #5a00b5;
            font-weight: 500;
            font-size: 16px;
            text-align: center;
        }

        .signup-link {
            font-size: 16px;
            color: #666;
            margin-top: 30px;
        }

        .signup-link a {
            color: #6e44ff;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .notice {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 20px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            display: none;
            z-index: 1002;
        }

        .notice h2 {
            font-size: 20px;
            color: #ff69b4;
            margin-bottom: 10px;
        }

        .notice p {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            text-align: center;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            cursor: pointer;
            color: #999;
        }

        @media (max-width: 768px) {
            .contact-container {
                padding: 20px;
            }

            .contact-container h1 {
                font-size: 28px;
            }

            .contact-container h2 {
                font-size: 22px;
            }

            .contact-container p,
            .contact-container ul li {
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            .contact-container {
                padding: 15px;
            }

            .contact-container h1 {
                font-size: 24px;
            }

            .contact-container h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/navbar.php'; ?>

    <div class="contact-container">
        <h1>Contact Our Support Team</h1>
        <p>
            We're here to help with any questions or issues you may have! At Task Tube, our dedicated support team is available to assist you 24/7. Whether you need help with your account, login, or have general inquiries, feel free to reach out.
        </p>

        <div class="contact-info">
            <h2>Contact Information</h2>
            <p><i class="fab fa-whatsapp"></i> WhatsApp Contact: <strong><a href="https://wa.me/+447438783028" target="_blank">+44 7438 783028</a></strong></p>
            <p><i class="far fa-clock"></i> Availability: <strong>24/7</strong></p>
            <p><i class="fas fa-hourglass-half"></i> Response Time: <strong>Usually within 24 hours</strong></p>
        </div>

        <div class="categories">
            <h2>We Can Help With:</h2>
            <ul>
                <li>Technical Support for Login/Access Issues</li>
                <li>Verification Requests</li>
            </ul>
        </div>

        <div class="cta">
            <p><i class="fab fa-whatsapp"></i> Send us a WhatsApp message anytime — we're ready to assist you!</p>
        </div>

        <p class="signup-link">
            Not yet a member? <a href="register.php">Sign Up Now</a> to start earning with Task Tube!
        </p>
    </div>

    <div class="notice" id="notice">
        <span class="close-btn" onclick="closeNotice()" aria-label="Close notice">×</span>
        <h2>Contact Task Tube</h2>
        <p>Need assistance? Our support team is here to help you 24/7 via WhatsApp.</p>
        <p>Reach out today to get started or resolve any issues!</p>
    </div>

    <?php include 'inc/footer.php'; ?>

    <!-- LiveChat Script -->
    <script>
        window.__lc = window.__lc || {};
        window.__lc.license = 15808029;
        (function(n,t,c){function i(n){return e._h?e._h.apply(null,n):e._q.push(n)}var e={_q:[],_h:null,_v:"2.0",on:function(){i(["on",c.call(arguments)])},once:function(){i(["once",c.call(arguments)])},off:function(){i(["off",c.call(arguments)])},get:function(){if(!e._h)throw new Error("[LiveChatWidget] You can't use getters before load.");return i(["get",c.call(arguments)])},call:function(){i(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0,n.type="text/javascript",n.src="https://cdn.livechatinc.com/tracking.js",t.head.appendChild(n)}};!n.__lc.asyncInit&&e.init(),n.LiveChatWidget=n.LiveChatWidget||e}(window,document,[].slice))
    </script>
    <noscript><a href="https://www.livechat.com/chat-with/15808029/" rel="nofollow">Chat with us</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>

    <script>
        // Set Active Navbar Link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.ham-menu ul li a');
            links.forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.parentElement.classList.add('active');
                }
            });
        });

        // Notice Popup
        function isNoticeShown() {
            return localStorage.getItem('noticeShownContact');
        }

        function setNoticeShown() {
            localStorage.setItem('noticeShownContact', true);
        }

        function showNotice() {
            if (!isNoticeShown()) {
                const notice = document.getElementById('notice');
                setTimeout(() => {
                    notice.style.display = 'block';
                    setNoticeShown();
                }, 1000);
            }
        }

        function closeNotice() {
            document.getElementById('notice').style.display = 'none';
            setNoticeShown();
        }

        window.addEventListener('load', showNotice);

        // Prevent right-click
        document.addEventListener('contextmenu', e => e.preventDefault());
    </script>
</body>
</html>
