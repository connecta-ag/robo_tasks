<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
use CAG\Robo\Task\loadTasks as CAGTasks;

class RoboFile extends \Robo\Tasks
{
    use CAGTasks;

    CONST NPM_BIN_PATH = 'node_modules/.bin/';
    CONST BASE_DIR = __DIR__;

    protected $context;
    protected $envVariables;

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        $this->envVariables = new Dotenv\Dotenv(self::BASE_DIR);
        $this->envVariables->load();

        $this->envVariables->required('APPLICATION_CONTEXT')->allowedValues(['Development', 'Staging', 'Production']);
        $this->context = getenv('APPLICATION_CONTEXT');
    }

    ##################################################
    ### Tasks ########################################
    ##################################################


    #### Watcher #####################################

    /**
     * Watch for assets changes and compile css & js for development context
     */
    function watchAssets()
    {
        $this->taskWatch()
            ->monitor(self::BASE_DIR . '/web/assets/JavaScriptSrc', function() {
                $this->compileJs(false);
            })
            ->monitor(self::BASE_DIR . '/web/assets/Scss', function() {
                $this->compileCss(false);
            })->run();
    }

    #### Build tasks ##################################

    /**
     * Build dependencies (npm etc.) and configure database by .env
     */
    function buildProjectDependencies()
    {
        $this->envVariables->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

        $this->taskNpmInstall()->run();

        $this->taskWriteToFile(self::BASE_DIR . '/web/typo3conf/conf.d/DatabaseCredentials.php')
            ->line('<?php')
            ->line('$GLOBALS[\'TYPO3_CONF_VARS\'][\'DB\'][\'host\'] = \'' . getenv('DB_HOST') . '\';')
            ->line('$GLOBALS[\'TYPO3_CONF_VARS\'][\'DB\'][\'database\'] = \'' . getenv('DB_NAME') . '\';')
            ->line('$GLOBALS[\'TYPO3_CONF_VARS\'][\'DB\'][\'username\'] = \'' . getenv('DB_USER') . '\';')
            ->line('$GLOBALS[\'TYPO3_CONF_VARS\'][\'DB\'][\'password\'] = \'' . getenv('DB_PASS') . '\';')
            ->run();

        $this->taskWriteToFile(self::BASE_DIR . '/web/typo3conf/conf.d/InstallToolPassword.php')
            ->line('<?php')
            ->line('$GLOBALS[\'TYPO3_CONF_VARS\'][\'BE\'][\'installToolPassword\'] = md5(\'wurst#\');')
            ->run();

        if(strtolower($this->context) != 'production') {
            $this->taskReplaceInFile('web/robots.txt')
                ->from('#CONTEXT_DEPENDED_CONFIGURATION#')
                ->to('Disallow: /')
                ->run();
        }
    }

    /**
     * Build development & production assets (css & js)
     * @param null $context Development context [production|development]
     */
    function buildAssets($context = null)
    {
        if(empty($context)) {
            $context = $this->context;
        }

        $this->say('Build assets for context <bg=yellow;fg=black>' . strtoupper($context) . '</bg=yellow;fg=black> ...');
        switch(strtolower($context)) {
            case 'production':
                // compile js
                $this->compileJs(true);
                $this->compileCss(true);

                break;
            case 'development':
                $this->compileJs(false);
                $this->compileCss(false);
                break;
        }
    }

    /**
     * Build critical css for homepage (default & arabic)
     *
     * Loads content of http://movenpick.com/en/ & http://movenpick.com/ar/ to generate critical css
     */
    function buildCritical() {

        $tmpPath = self::BASE_DIR . '/web/typo3temp';
        $this->_mkdir($tmpPath);

        $this->taskParallelExec()
            ->process('wget http://movenpick.com/en/ -O ' . $tmpPath . '/index.html')
            ->process('wget http://movenpick.com/ar/ -O ' . $tmpPath . '/index_arabic.html')
            ->run();

        $this->taskExec('node ./build/fe/tasks/criticalCss ' . $tmpPath)
            ->dir(self::BASE_DIR)
            ->run();
    }

    /**
     * Optimize SVG's and build icon css from svg icon files
     */
    function buildIcons() {
        $this->optimizeSvgIcons();
        $this->taskExec('node ' . self::BASE_DIR . '/build/fe/tasks/icons')
            ->dir(self::BASE_DIR)
            ->run();
        $this->_remove(self::BASE_DIR . '/web/assets/Icons/Content/icons.data.png.css');
        $this->_remove(self::BASE_DIR . '/web/assets/Icons/Content/icons.fallback.css');
    }

    #### Optimize tasks ##################################

    /**
     * Optimize images with imageoptim. !Attention! Additionally you need to download https://imageoptim.com
     */
    function optimizeImages() {
        $this->taskExec(self::NPM_BIN_PATH . 'imageoptim -a -q -d ' . self::BASE_DIR . '/web/assets/Images')
            ->dir(self::BASE_DIR)
            ->run();
    }

    /**
     * Reduce svg overhead. Used in build:icons
     */
    function optimizeSvgIcons() {
        $this->taskExec(self::NPM_BIN_PATH . 'svgo -f ' . self::BASE_DIR . '/web/assets/Icons/Content/svg')
            ->dir(self::BASE_DIR)
            ->run();
    }

    #### Test tasks ##################################

    /**
     * Create references for css regression tests [environment = live]
     */
    function testCreateVisualReferences() {
        $this->taskExec('npm run reference -- --configPath=' . self::BASE_DIR . '/tests/visual/testProduction.json')
            ->dir(self::BASE_DIR . '/node_modules/backstopjs/')
            ->run();
    }

    /**
     * Test css regressions [environment = live]
     */
    function testVisualReferences() {
        $this->taskExec('npm run test -- --configPath=' . self::BASE_DIR . '/tests/visual/testProduction.json')
            ->dir(self::BASE_DIR . '/node_modules/backstopjs/')
            ->run();
    }

    /**
     * Functional tests
     *
     * For local test start with:
     *      ./vendor/consolidation/robo/robo test:functional http://www.movenpick.com 1
     *
     * For jenkins (with selenium grid) start with:
     *      ./vendor/consolidation/robo/robo test:functional http://www.movenpick.com 0
     *              OR
     *      ./vendor/consolidation/robo/robo test:functional http://www.movenpick.com
     *
     * @param string $domain vHost to test against
     * @param bool $local If local is set to true wdio starts standalone selenium server
     */
    function testFunctional($domain = 'http://www.movenpick.com', $local = false) {

        if($local) {
            // if local == true start standalone selenium server
            $configFile = 'wdio_local.conf.js';
        }else {
            $configFile = 'wdio.conf.js';
        }

        $this->taskExec('./wdio -b ' . $domain . ' ' . self::BASE_DIR . '/tests/functional/config/' . $configFile)
            ->dir(self::NPM_BIN_PATH)
            ->run();
    }

    /**
     * Sync files and database from remote to local
     */
    function migrateRemoteToLocal() {

        $this->envVariables->required([
            'CONTENT_SYNC_HOST', 'CONTENT_SYNC_FOLDERS', 'CONTENT_SYNC_SSH_USER',
            'CONTENT_SYNC_FILES_HOST_BASE_PATH', 'CONTENT_SYNC_FILES_LOCAL_BASE_PATH_CORRECTION'
        ]);

        $this->taskSyncFiles()
            ->host(getenv('CONTENT_SYNC_HOST'))
            ->folders(getenv('CONTENT_SYNC_FOLDERS'))
            ->remoteUser(getenv('CONTENT_SYNC_SSH_USER'))
            ->remoteBasePath(getenv('CONTENT_SYNC_FILES_HOST_BASE_PATH'))
            ->localBasePath(self::BASE_DIR)
            ->localPathCorrection(getenv('CONTENT_SYNC_FILES_LOCAL_BASE_PATH_CORRECTION'))
            ->run();

        $this->envVariables->required([
            'DB_NAME', 'DB_PASS', 'CONTENT_SYNC_HOST', 'CONTENT_SYNC_SSH_USER', 'CONTENT_SYNC_SSH_KEY',
            'CONTENT_SYNC_DATABASE_REMOTE_HOST', 'CONTENT_SYNC_DATABASE_REMOTE_DB_USER', 'CONTENT_SYNC_DATABASE_REMOTE_DB_NAME',
            'CONTENT_SYNC_DATABASE_REMOTE_DB_PASS'
        ]);

        $this->taskPullDbViaSsh()
            ->sshHost(getenv('CONTENT_SYNC_HOST'))
            ->sshUser(getenv('CONTENT_SYNC_SSH_USER'))
            ->sshKey(getenv('CONTENT_SYNC_SSH_KEY'))
            ->remoteDbHost(getenv('CONTENT_SYNC_DATABASE_REMOTE_HOST'))
            ->remoteDbUser(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_USER'))
            ->remoteDbName(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_NAME'))
            ->remoteDbPass(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_PASS'))
            ->localDbName(getenv('DB_NAME'))
            ->localDbPass(getenv('DB_PASS'))
            ->run();
    }

    ##################################################
    ### Start - Helper methods #######################
    ##################################################

    /**
     * Compile Javascript
     *
     * @param bool $compress Compress output for production environment
     */
    private function compileJs($compress = true)
    {
        $this->say('Compile js ...');
        // build js
        $this->taskExec(self::NPM_BIN_PATH . 'browserify ./web/assets/JavaScriptSrc/Main.js -d > ./web/assets/JavaScript/main.js')
            ->dir(self::BASE_DIR)
            ->run();

        if($compress === true) {
            $this->uglifyJs();
            $this->_remove(self::BASE_DIR . '/web/assets/JavaScript/main.js');
        }
    }

    /**
     * Uglify js
     */
    private function uglifyJs()
    {
        $this->say('Uglify js ...');
        $this->taskExec(self::NPM_BIN_PATH . 'uglifyjs --compress --mangle -o ./web/assets/JavaScript/main.min.js -- ./web/assets/JavaScript/main.js')
            ->dir(self::BASE_DIR)
            ->run();
    }

    /**
     * Build stylesheets from scss
     *
     * @param bool $compress minify stylesheets for production
     */
    private function compileCss($compress = true)
    {
        $additionalOutputFilename = '';
        if($compress === true) {
            $this->say('Compile minified css ...');
            $sassConfig = ' --output-style compressed';
            $additionalOutputFilename = '.min';
        }else {
            $this->say('Compile dev css ...');
            $sassConfig = '--source-comments true --source-map-embed true --source-map true --source-map-root /assets/Scss/';
        }

        $this->taskParallelExec()
            ->process(self::NPM_BIN_PATH . 'node-sass ' . $sassConfig . ' -o ./web/assets/Stylesheets/ ./web/assets/Scss/style.scss && ' . self::NPM_BIN_PATH . 'postcss --use autoprefixer --autoprefixer.browsers \'> 1%,last 2 versions,iOS 8.1\' < ./web/assets/Stylesheets/style.css -o ./web/assets/Stylesheets/style' . $additionalOutputFilename . '.css | rm ./web/assets/Stylesheets/style.css')
            ->process(self::NPM_BIN_PATH . 'node-sass ' . $sassConfig . ' -o ./web/assets/Stylesheets/ ./web/assets/Scss/style_ar.scss && ' . self::NPM_BIN_PATH . 'postcss --use autoprefixer --autoprefixer.browsers \'> 1%,last 2 versions,iOS 8.1\' < ./web/assets/Stylesheets/style_ar.css -o ./web/assets/Stylesheets/style_ar' . $additionalOutputFilename . '.css | rm ./web/assets/Stylesheets/style_ar.css')
            ->process(self::NPM_BIN_PATH . 'node-sass ./web/assets/Scss/rte.scss ./web/assets/Stylesheets/rte.css')
            ->run();
    }
}