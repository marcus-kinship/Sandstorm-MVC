<style>
    /* Shared by 404.php and 500.php: the Sandstorm look without an image, so the pages stay about 2 KB */
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        box-sizing: border-box;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        color: #ffffff;
        background: linear-gradient(180deg, #0d2f74 0%, #2a63c0 42%, #8fb3e0 58%, #f0b35e 60%, #c97a35 78%, #8e4d22 100%) fixed;
    }

    .window {
        width: 560px;
        max-width: 100%;
        padding: 0 40px 36px;
        box-sizing: border-box;
        border-radius: 20px;
        background: linear-gradient(144deg, rgba(37, 37, 37, 0.35) 0%, rgb(10 10 10 / 25%) 47%);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 1px 1px 1px #ffffff29, -1px -1px 1px #ffffff29;
        text-shadow: 1px 1px 1px #000000;
    }

    .title {
        height: 64px;
        font: 17px sans-serif;
        line-height: 64px;
    }

    h1 {
        margin: 8px 0 0;
        font: 27px sans-serif;
    }

    p {
        margin: 16px 0 0;
        line-height: 22px;
    }

    .id {
        color: rgb(255 255 255 / 70%);
        font-size: 14px;
    }

    .aero-button {
        display: inline-block;
        margin-top: 8px;
        padding: 9px 20px;
        border-radius: 28px;
        color: #ffffff;
        font-size: 13px;
        text-decoration: none;
        background: linear-gradient(to top, rgb(53 53 53 / 50%) 12%, rgb(0 0 0 / 50%) 33%, rgb(0 0 0 / 50%) 50%, rgb(39 39 39 / 50%) 51%, rgb(104 104 104 / 50%) 100%);
        box-shadow: inset 0 0 1px 1px rgb(255 255 255 / 12%), 0 6px 14px -6px #ffb300;
    }
</style>
