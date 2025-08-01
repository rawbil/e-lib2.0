import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
                bitcount: ["Bitcount", ...defaultTheme.fontFamily.mono],
                inter: ["Inter", "sans-serif"],
            },
            colors: {
                dark: "#000",
                light: "#fff",
                blue: "rgb(54, 162, 235)",
                yellow: 'rgb(255, 205, 86)'
            },
            screens: {
                "1800px": "1800px",
                "1500px": "1500px",
                '1360px': '1360px',
                "1300px": "1300px",
                '1230px': '1230px',
                '1150px': '1150px',
                '1050px': '1060px',
                "1000px": "1000px",
                "920px": '920px',
                '890px': '890px',
                '730px': '730px',
                "630px": "630px",
                "600px": "600px",
                '440px': '440px',
                '390px': '390px',
                "200px": "200px",
            },
        },
    },

    plugins: [forms],
};
