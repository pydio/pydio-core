module.exports = {
    options: {
        loose: 'all'
    },
    dist: {
        files: [
            {
                mode: {loose: true},
                expand: true,
                cwd: 'res/js/es6/',
                src: ['**/*.es6'],
                dest: 'res/js/core/',
                ext: '.js'
            }
        ]
    },
    materialui: {
        files: [
            {
                mode: {loose: false},
                expand: true,
                cwd: 'node_modules/material-ui-legacy/src/',
                src: ['**/*.js', '**/*.jsx'],
                dest: 'node_modules/material-ui-legacy/lib/',
                ext: '.js'
            }]
    },
    pydio:{
        files: [
            {
                expand: true,
                cwd: 'res/js/ui/reactjs/jsx',
                src: ['**/*.js'],
                dest: 'res/js/ui/reactjs/build/',
                ext: '.js'
            }
        ]
    }
};