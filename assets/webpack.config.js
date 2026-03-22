/**
 * Webpack configuration
 */
import path from 'path';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import CopyWebpackPlugin from 'copy-webpack-plugin';
import DependencyExtractionWebpackPlugin from '@wordpress/dependency-extraction-webpack-plugin';
import webpack from 'webpack';
import { globSync } from 'glob';
import { CleanWebpackPlugin } from 'clean-webpack-plugin';
import { fileURLToPath } from 'url';


// Fix for __dirname in ES modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Helper to build named entries
 */
const buildEntries = (pattern, baseDir) => {
    const entries = {};

    globSync(pattern).forEach((file) => {
        let ext = path.extname(file).replace('.', ''); // js | scss
        ext = ext === 'scss' ? 'css' : ext;
        const name = path
            .relative(baseDir, file)
            .replace(path.extname(file), '');

        entries[name] = path.resolve(__dirname, file);
    });

    return entries;
};

/**
 * JS entries
 */
const jsEntries = {
    'js/helperbox': path.resolve(__dirname, 'src/js/helperbox.js'),
    'js/moodle-integraton': path.resolve(__dirname, 'src/js/moodle-integraton.js'),
    'js/admin': path.resolve(__dirname, 'src/js/admin.js'),
    'js/login': path.resolve(__dirname, 'src/js/login.js'),
    ...buildEntries('src/blocks/**/*.js', 'src'),
};

/**
 * SCSS entries
 */
const cssEntries = {
    'css/helperbox': path.resolve(__dirname, 'src/scss/helperbox.scss'),
    'css/admin': path.resolve(__dirname, 'src/scss/admin.scss'),
    'css/login': path.resolve(__dirname, 'src/scss/login.scss'),
    ...buildEntries('src/blocks/**/*.scss', 'src'),
};



const exportModuleRules = [
    // JavaScript / JSX (React)
    {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
            loader: 'babel-loader',
            options: {
                presets: [
                    '@babel/preset-env',
                    '@babel/preset-react'
                ],
            },
        },
    },

    // SCSS, SASS, CSS → CSS (with Tailwind, PostCSS, Autoprefixer)
    {
        test: /\.(sa|sc|c)ss$/,
        use: [
            MiniCssExtractPlugin.loader,
            'css-loader',
            {
                loader: 'postcss-loader',
                options: {
                    postcssOptions: {
                        plugins: [
                            'autoprefixer',
                        ],
                    },
                },
            },
            'sass-loader',
        ],
    },

    // SVG → React component (for @wordpress/icons or custom icons)
    {
        test: /\.svg$/,
        issuer: /\.(js|jsx)$/,
        use: [
            {
                loader: '@svgr/webpack',
                options: {
                    svgoConfig: {
                        plugins: [{ name: 'removeViewBox', active: false }],
                    },
                },
            },
        ],
    },

    // Other assets (images, fonts) as files
    {
        test: /\.(png|jpe?g|gif|webp|woff2?|ttf|eot|ico|svg|json)$/,
        type: 'asset/resource',
        generator: {
            filename: '[path][name][ext]',
        },
    }
]

/**
 * Webpack plugins
 */
const exportPlugins = [

    new RemoveEmptyScriptsPlugin(),

    new CleanWebpackPlugin({
        cleanOnceBeforeBuildPatterns: ['**/*', '!*.php', '!block.json'],
    }),

    new MiniCssExtractPlugin({
        filename: '[name].css',
    }),

    // Critical: Extract WordPress dependencies as externals
    new DependencyExtractionWebpackPlugin({
        injectPolyfill: true,
        combineAssets: true,
    }),

    // Copy PHP files, block.json, etc. to build folder
    new CopyWebpackPlugin({
        patterns: [
            { from: 'src/blocks/**/*.php', to: '[path][name][ext]', noErrorOnMissing: true, },
            { from: 'src/blocks/**/block.json', to: '[path][name][ext]', noErrorOnMissing: true, },
            // Add more if needed (e.g., languages/*.mo)
        ],
    }),

    // Show build progress
    new webpack.ProgressPlugin(),
];

/**
 * Webpack configuration export
 */
export default () => {
    return {
        entry: {
            ...jsEntries,
            ...cssEntries,
        },
        context: path.resolve(__dirname, 'src'),

        output: {
            path: path.resolve(__dirname, 'build'),
            filename: '[name].js',
            clean: false, // OR Handled by CleanWebpackPlugin
        },

        module: {
            rules: exportModuleRules,
        },

        plugins: exportPlugins,

        resolve: {
            extensions: ['.js', '.jsx', '.json'],
        },

        performance: {
            maxAssetSize: 1048576,
            maxEntrypointSize: 1048576,
            hints: 'warning',
        },

        externals: {
            // Optional: Add manual externals if needed
        },
    };
};