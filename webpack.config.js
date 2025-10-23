/**
 * WordPress Scripts Webpack Configuration
 * 
 * This extends the default @wordpress/scripts webpack config
 * Customize as needed for your plugin
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    
    // Entry points - explicitly define our source files
    entry: {
        'editor/index': path.resolve(process.cwd(), 'src/editor', 'index.js'),
    },
    
    // Output configuration
    output: {
        ...defaultConfig.output,
        path: path.resolve(process.cwd(), 'build'),
    },
    
    // Externals - WordPress globals that shouldn't be bundled
    externals: {
        ...defaultConfig.externals,
        jquery: 'jQuery',
    },
};
