<?php
// Build steps for tenma --watch (and tenma build): the stylesheet, from every template that uses its classes

return [
    new Tenma\Hook(
        inputs: ['assets/app.css', 'views/**/*.php', 'src/**/*.php'],
        outputs: ['public/app.css'],
        run: ['tailwindcss', '-i', 'assets/app.css', '-o', 'public/app.css', '--minify'],
        name: 'css',
    ),
];
