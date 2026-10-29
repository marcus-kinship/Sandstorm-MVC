<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><? print $title; ?></title>
    <META NAME="ROBOTS" CONTENT="NOINDEX, FOLLOW">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/devibeans.min.css" rel="stylesheet">

    <script>
        $(function() {
            hljs.highlightAll();
            // Bakgrunden ligger inbäddad i CSS:en; ladda samma bild för att veta när den är klar
            var image = new Image();

            // När bilden har laddats klart
            $(image).on('load', function() {
                var preload = $('.preload');
                preload.addClass('fadeout');

                // Efter en kort fördröjning, ta bort preload-elementet
                setTimeout(function() {
                    preload.hide();
                    setTimeout(function() {
                        var windowElement = $('.window');
                        windowElement.addClass('loaded');
                        setTimeout(function() {
                            var contentElement = $('.content');
                            contentElement.addClass('loaded');
                        }, 600);
                    }, 800);
                }, 200);
            });

            image.src = getComputedStyle(document.body).backgroundImage.slice(4, -1).replace(/"/g, "");


            $(".aero-button.system-exception").click(function() {
                $(".dialog").hide();
                $(".dialog.system-exception").show();
                $(".dialog").css({
                    opacity: 0
                }).animate({
                    opacity: 1
                }, 1000);
            });

            $(".aero-button.post-data").click(function() {
                $(".dialog").hide();
                $(".dialog.post-data").show();
                $(".dialog").css({
                    opacity: 0
                }).animate({
                    opacity: 1
                }, 1000);
            });

            $(".aero-button.classes").click(function() {
                $(".dialog").hide();
                $(".dialog.classes").show();
                $(".dialog").css({
                    opacity: 0
                }).animate({
                    opacity: 1
                }, 1000);
            });

            function moveAfterElement(event) {
                const x = event.pageX - $(this).offset().left;
                const y = $(this).innerHeight() + 8;

                $(this)
                    .find(".after")
                    .removeClass("pulse")
                    .css({
                        left: `${x}px`,
                        top: `${y}px`
                    });
                const middlePoint = $(this).height() / 2;
                const maxOpacityHeight = middlePoint;
                const opacity = Math.min(
                    1,
                    Math.abs(middlePoint - y) / maxOpacityHeight
                );
                $(this).find(".aero-button after").css({
                    opacity: opacity
                });
            }

            const y = $(".aero-button").innerHeight();
            const x = $(".aero-button").innerWidth() / 2;

            $(".aero-button")
                .find(".after")
                .addClass("pulse")
                .css({
                    left: `${x}px`,
                    top: `${y}px`
                });

            $(".aero-button").on("mouseleave", function() {
                const y = $(this).innerHeight() + 8;
                const x = $(this).innerWidth() / 2;

                $(this)
                    .find(".after")
                    .addClass("pulse")
                    .css({
                        left: `${x}px`,
                        top: `${y}px`
                    });
            });
            $(".aero-button").on("mousemove", moveAfterElement);

            $(".aero-button").each(function() {
                var yc = $(this).innerHeight() + 8;
                var xc = $(this).innerWidth() / 2;
                $(this)
                    .find(".after")
                    .addClass("pulse")
                    .css({
                        left: `${xc}px`,
                        top: `${yc}px`
                    });
            });
        });
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track:horizontal {
            height: 10px;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-track {

            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-thumb {
            background-color: #00000029;
            border-radius: 4px;
            -webkit-border-radius: 4px;
            -moz-border-radius: 4px;
            -ms-border-radius: 4px;
            -o-border-radius: 4px;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-button:single-button {
            border-width: 0px;
            width: 5px;
            height: 5px;
            background-color: transparent;
            border-color: transparent transparent #555555 transparent;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-button:single-button:hover {
            border-color: transparent transparent #777777 transparent;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-button:single-button {
            border-width: 0px;
            border-color: #555555 transparent transparent transparent;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-button:single-button:increment:hover {
            border-color: #777777 transparent transparent transparent;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-thumb:hover {
            background-color: #00000049;
            transition: all 1000ms linear;
        }

        ::-webkit-scrollbar-corner {
            background-color: #0000000;
        }

        html,
        body {
            padding: 0px;
            margin: 0px;
        }

        body {
            background-image: url(data:image/webp;base64,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);
            background-repeat: no-repeat;
            background-size: cover;
            background-clip: border-box;
        }

        .row {
            display: flex;
            justify-content: center;
        }

        .window {
            width: 888px;
            min-height: 600px;
            backdrop-filter: blur(10px);
            box-shadow: 1px 1px 1px #ffffff29, -1px -1px 1px #ffffff29;
            margin-bottom: 50px;
            margin-top: 94px;
            justify-content: center;
            background-color: rgb(0 0 0 / 30%);
            background: linear-gradient(144deg,
                    rgba(37, 37, 37, 0.3) 0%,
                    rgb(10 10 10 / 20%) 47%);
            border-radius: 20px;
            overflow: hidden;
            transform: translateY(40px);
            opacity: 0;
            transition: opacity 1s ease, transform 1s ease;
        }

        .window .content {
            padding-bottom: 43px;
            opacity: 0;
            transition: opacity 1s ease, transform 1s ease;
            /* Mjuka övergångar för opacity och transform */
        }

        .window .dialog {
            margin-left: 43px;
            margin-right: 43px;
            min-height: 415px;
        }

        .title {
            height: 76px;
            display: flex;
            margin-left: 43px;
            margin-right: 43px;
            color: rgb(255 255 255 / 96%);
            font: 19px sans-serif;
            font-weight: normal;
            line-height: 76px;
            text-shadow: 1px 1px 1px #000000;
            align-items: center;
        }

        .progress-bar {
            margin-top: 88px;
            width: 608px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .progress-bar div {
            background-color: #ffffff;
            height: 11px;
            border-radius: 11px;
            box-shadow: 1px 1px 8px #00000063;
        }

        .first,
        .last {
            width: 25%;
        }

        .secondary {
            width: 50%;
        }

        .progress-bar-text {
            color: #ffffff;
            font: 18px sans-serif;
            font-weight: lighter;
            line-height: 72px;
            text-shadow: 1px 1px 4px #000000;
            height: 72px;
        }

        .h1 {
            color: #ffffff;
            font: 27px sans-serif;
            text-shadow: 1px 1px 1px #000000;
            display: flex;
            align-content: center;
            align-items: center;
            column-gap: 14px;
        }

        .p {
            color: #ffffff;
            font: 16px sans-serif;
            margin-top: 18px;
            text-shadow: 1px 1px 1px #000000;
            line-height: 22px;
        }

        .button {
            box-shadow: 0px 0px 0px 1px #00000063, inset 0px 0px 0px 1px #18508e,
                inset 0px 1px 0px 2px #18508e;
        }

        .aero-button {
            position: relative;
            display: inline-block;
            padding: 8px 19px 8px 19px;
            background: linear-gradient(to top,
                    rgb(53 53 53 / 50%) 1%,
                    rgb(53 53 53 / 50%) 12%,
                    rgb(0 0 0 / 50%) 33%,
                    rgb(0 0 0 / 50%) 50%,
                    rgb(39 39 39 / 50%) 51%,
                    rgb(104 104 104 / 50%) 100%);
            color: #ffffff;
            font: 11px system-ui;
            text-shadow: 1px 1px 1px #000000;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: inset 0px 0px 1px 1px rgb(255 255 255 / 12%),
                0px 0px 1px 1px rgb(0 0 0 / 0%);
            cursor: default;
        }

        .pulse {
            animation: pulse 3s infinite;
        }

        .button-container {
            column-gap: 25px;
            display: flex;
            padding-top: 35px;
            margin-left: 43px;
            margin-right: 43px;
            flex-direction: row;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        .aero-button .after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            filter: blur(8px);
            background: radial-gradient(circle, #ffc107 0%, #ffb300 100%);
            transition: 0.5s ease;
            z-index: -1;
        }

        .button-text {
            color: #333333;
            font-family: Arial, sans-serif;
            font-size: 15px;
            font-weight: bold;
        }

        .stack {
            width: 400px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue",
                sans-serif;
            color: white;
            max-width: 300px;
            min-width: calc(20%);
            font-size: 12px;
        }

        .stack .title {
            padding: 0px;
            margin: 0px;
        }

        .stack ul {
            list-style-type: none;
            padding-left: 0px;
            display: flex;
            flex-direction: column;
            margin-top: 0px;
            margin-bottom: 0px;
            overflow-y: scroll;
            height: 205px;
        }

        .stack ul li {
            display: flex;
            align-items: center;
            column-gap: 20px;
        }

        .stack ul li .icon {
            display: flex;
            width: 80px;
            justify-content: center;
        }

        .stack ul li .icon .white {
            fill: white;
            width: 28px;
            height: 28px;
        }

        .stack ul li .classname {
            border-top: 1px solid #66666680;
            padding: 12px;
            padding-bottom: 14px;
            line-height: 16px;
            width: 100%;
        }

        .code {
            width: 100%;
            background-color: #00000040;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
            padding-bottom: 8px;
            overflow: auto;
        }

        .code .filname {
            text-align: left;
            display: flex;
            color: rgb(255 255 255 / 96%);
            font-weight: normal;
            font-size: 16px;
            padding: 8px 10px;
            text-shadow: 1px 1px 1px #000000;
        }

        table {
            padding: 0px;
            margin: 0px;
            border-collapse: collapse;

            width: 100%;
        }

        tr.row .line {
            color: white;
            font-size: 12px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue",
                sans-serif;
            font-weight: normal;
            text-align: right;
            padding-right: 2px;
            padding-left: 10px;
            height: 18px;
            line-height: 17px;
        }

        tr.row td.text {
            color: white;
            font-size: 12px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue",
                sans-serif;
            font-weight: normal;
            text-align: left;
            width: 100%;
            padding-right: 2px;
            padding-left: 10px;
            height: 18px;
            margin-right: 10px;
            margin-left: 6px;
            line-height: 17px;
        }

        .list tbody td {
            font-size: 12px;
            padding: 8px 10px;
            background-color: #00000040;
        }

        .list thead td {
            padding: 8px 10px;

        }

        table.list {
            border-collapse: separate;
            border-spacing: 0px 1px;

        }

        .icon {
            fill: #ffffff;
            width: 32px;
            height: 32px;
        }

        .head {
            background-color: #00000040;
        }

        @keyframes error {
            0% {
                background-color: rgb(110, 2, 2);
            }

            100% {
                background-color: rgb(163, 0, 0);
            }
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                /* Start opacity */
            }

            50% {
                opacity: 0;
                /* Max opacity */
            }

            100% {
                opacity: 1;
                /* Tillbaka till start opacity */
            }
        }

        .fadeout {
            animation-name: fadeout;
            animation-duration: 1s;
        }

        @keyframes fadeout {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        .error {

            animation: error 2s infinite alternate;
            border-radius: 30px;
        }

        /* Stilar för skärmar mindre än 1024px */
        @media only screen and (max-width: 1024px) {
            .window {
                margin-top: 45px;
                transition: margin-top 1s ease;
                margin-bottom: 20px;
            }
        }

        /* Stilar för skärmar mindre än 625px */
        @media only screen and (max-width: 625px) {
            .window {
                margin-top: 10px;
                transition: margin-top 1s ease;
                margin-bottom: 10px;
            }

            .title {
                margin-left: 21px;
                margin-right: 21px;
                transition: margin-left 1s ease, margin-right 1s ease;
            }

            .window .content {
                padding-bottom: 21px;
                transition: padding-bottom 1s ease;
            }

            .window .content .dialog,
            .window .content .button-container {
                margin-left: 21px;
                margin-right: 21px;
                transition: margin-left 1s ease, margin-right 1s ease;
            }
        }

        .preload {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: black;
            opacity: 1;
            /* Håll opacity 1 tills bilden har laddats */
            z-index: 9999;
            transition: opacity 1s ease;
        }

        .window.loaded {
            opacity: 1;
            transform: translateY(0);
        }

        .content.loaded {
            opacity: 1;
        }

        .code>div:not(.filname) {
            padding: 8px 10px;
            font-size: 12px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            font-weight: normal;
            color: #ffffff;
        }

        .erorr-type {
            display: inline-block;
            color: #ffffff;
            font: 16px sans-serif;
            margin-top: 15px;
            text-shadow: 1px 1px 1px #000000;
        }

        .red {
            color: red;
        }

        pre {
            margin: 0px;
            background-color: transparent;
            white-space: pre-line;
        }

        code.language-sql {
            background-color: transparent;
            white-space: pre-line;
            padding: 0px !important;
            text-shadow: 1px 1px 1px #000000;
        }

        .text {
            color: #ffffff;
            text-shadow: 1px 1px 1px #000000;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            font-weight: normal;
         
        }

        .messages>div:nth-child(2) {
            display: inline;
        }

        .dialog.classes,
        .dialog.post-data {
            display: none;
        }
    </style>

</head>

<body>
    <div class="preload"></div>