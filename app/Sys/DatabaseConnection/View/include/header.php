<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo _('SQL Frågor som har körts'); ?></title>
    <META NAME="ROBOTS" CONTENT="NOINDEX, FOLLOW">
    <META NAME="ROBOTS" CONTENT="INDEX, NOFOLLOW">
    <META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/devibeans.min.css" rel="stylesheet">

    <script>
        $(function () {
            hljs.highlightAll();
            var image = new Image();
            setTimeout(function () { image.src = getComputedStyle(document.body).backgroundImage.slice(4, -1).replace(/"/g, ""); });

            $(image).on('load', function () {
                var preload = $('.preload');
                preload.addClass('fadeout');

                setTimeout(function () {
                    preload.hide();
                    setTimeout(function () {
                        var windowElement = $('.window');
                        windowElement.addClass('loaded');
                        setTimeout(function () {
                            var contentElement = $('.content');
                            contentElement.addClass('loaded');
                        }, 600);
                    }, 800);
                }, 200);
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
            background-image: url(<?php print include dirname(__DIR__, 3) . '/Views/background.php'; ?>);
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
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
            width: 14px;
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