module.exports = {
    content: [
        "./assets/**/*.{js,jsx,ts,tsx,vue,twig}",
        "./templates/**/*.twig",
        "./templates/**/*.html.twig",
    ],
    theme: {
        extend: {},
    },
    plugins: [require('daisyui')],
}