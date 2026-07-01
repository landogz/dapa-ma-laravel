<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>DAPE-MA Live Chat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #ffffff;
        }
        #bp-embedded-webchat {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 100vh;
        }
        .bpFab { display: none !important; }
        .bpWebchat {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            max-height: 100% !important;
        }
    </style>
    <script src="https://cdn.botpress.cloud/webchat/v3.6/inject.js"></script>
    <script src="https://files.bpcontent.cloud/2026/07/01/16/20260701161618-5PW46XBP.js" defer></script>
</head>
<body>
    <div id="bp-embedded-webchat"></div>
    <script>
        window.botpress.on('webchat:initialized', function () {
            window.botpress.open();
        });
    </script>
</body>
</html>
