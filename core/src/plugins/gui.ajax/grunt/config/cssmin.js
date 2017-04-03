module.exports = {
    options: {
        shorthandCompacting: false,
        roundingPrecision: -1
    },
    target: {
        files: {
            'res/themes/material/css/allz.css': [
                'res/themes/material/css/pydio.css',
                'res/themes/common/css/animate-custom.css',
                'res/themes/common/css/chosen.css',
                'res/themes/common/css/media.css'
            ]
        }
    }
};