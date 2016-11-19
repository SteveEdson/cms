module.exports = function(grunt) {
    // Project Configuration
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        watch: {
            sass: {
                files: ['src/resources/sass/*.scss'],
                tasks: 'sass'
            },
            craftjs: {
                files: ['src/resources/js/craft/*.js'],
                tasks: ['concat', 'uglify:craft']
            },
            otherjs: {
                files: ['src/resources/js/*.js', '!src/resources/js/craft.js'],
                tasks: ['uglify:other']
            }
        },
        sass: {
            options: {
                style: 'compact',
                unixNewlines: true
            },
            dist: {
                expand: true,
                cwd: 'src/resources/sass',
                src: '*.scss',
                dest: 'src/resources/css',
                ext: '.css'
            }
        },
        concat: {
            craft: {
                options: {
                    banner: '/*! <%= pkg.name %> <%= pkg.version %> - <%= grunt.template.today("yyyy-mm-dd") %> */\n' +
                    '(function($){\n\n',
                    footer: '\n})(jQuery);\n',
                },
                src: [
                    'src/resources/js/craft/craft.js',
                    'src/resources/js/craft/Base*.js',
                    'src/resources/js/craft/*.js',
                    '!(src/resources/js/craft/craft.js|src/resources/js/craft/Base*.js)'
                ],
                dest: 'src/resources/js/craft.js'
            }
        },
        uglify: {
            options: {
                sourceMap: true,
                preserveComments: 'some',
                screwIE8: true
            },
            craft: {
                src: 'src/resources/js/craft.js',
                dest: 'src/resources/js/compressed/craft.js'
            },
            other: {
                expand: true,
                cwd: 'src/resources/js',
                src: ['*.js', '!craft.js'],
                dest: 'src/resources/js/compressed'
            }
        },
        jshint: {
            options: {
                expr: true,
                laxbreak: true,
                loopfunc: true, // Supresses "Don't make functions within a loop." errors
                shadow: true,
                strict: false,
                '-W041': true,
                '-W061': true
            },
            beforeconcat: [
                'gruntfile.js',
                'src/resources/js/*.js',
                '!src/resources/js/craft.js',
                'src/resources/js/craft/*.js'
            ],
            afterconcat: [
                'src/resources/js/craft.js'
            ]
        }
    });

    //Load NPM tasks
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-jshint');

    // Default task(s).
    grunt.registerTask('default', ['sass', 'jshint:beforeconcat', 'concat', 'jshint:afterconcat', 'uglify']);
};