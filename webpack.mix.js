const webpack = require("webpack");
const mix = require("laravel-mix");
const StyleLintPlugin = require('stylelint-webpack-plugin');

// Set up project folders
const srcFolder = 'client/src';
const distFolder = 'client/dist';

if (process.env.NODE_ENV === 'development') {
  mix.webpackConfig({
    module: {
      rules: [
        {
          enforce: 'pre',
          test: /\.(js)$/,
          exclude: /node_modules/,
          loader: 'eslint-loader',
        },
      ],
    },
    plugins: [
      new StyleLintPlugin({
        context: srcFolder,
        files: ['**/*.{scss,js}'],
      }),
    ],
    devtool: 'inline-source-map',
  });

  mix.sourceMaps();
}

// Disable auto-generated <type>.LICENSE file
mix.options({
  terser: {
    extractComments: false,
  }
})

mix.js(`${srcFolder}/javascript/main.js`, distFolder)
  .sass(`${srcFolder}/scss/main.scss`, distFolder);

