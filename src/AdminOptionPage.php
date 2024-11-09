<?php

namespace Suovawp;

use Suovawp\Utils\FormField;

/**
 * @phpstan-import-type Field form FormField
 *
 * @phpstan-type TabRaw array{id:string,title:string,fields:Field[]|string}
 * @phpstan-type Tab array{id:string,title:string,fields:Field[]}
 * @phpstan-type SettingsRaw Field[]|array{id?:string,active_tab?:string,tabs:TabRaw[]}|array{id:string,fields:Field[]}
 * @phpstan-type Settings Field[]|array{id?:string,active_tab?:string,tabs:Tab[]}|array{id:string,fields:Field[]}
 */
class AdminOptionPage extends AdminPageModel
{
    /** @var Settings|null */
    protected $settings;

    /** @var SettingsRaw|null */
    protected $settingsRaw;

    /** @var string|null */
    protected $keyOptionBasename;

    protected $optionBasenamePrefix = 'admin/settings/';

    protected $optionBasenameSuffix = '/index';

    protected $topName = 'suovawp_settings';

    public function load()
    {
        $this->ctx->assets->media()->i18n();
        $this->handleFormAction();
    }

    protected function handleFormAction()
    {
        if (empty($_POST['submit_type']) || empty($_POST[$this->topName])) {
            return;
        }
        if (empty($_POST['_wpnonce']) || !$this->checkToken($_POST['_wpnonce'])) {
            wp_die('Unauthorized', 403);
        }

        $submitType = $_POST['submit_type'];
        $tabId = $_POST['active_tab'] ?? null;
        if ($this->hasTabs()) {
            $settings = $this->getTab($tabId);
            if (!isset($settings)) {
                wp_die('Invalid tab', 400);
            }
        } else {
            $settings = $this->getSettings();
        }
        $fields = $this->getTopSettingsFields($tabId);
        if (!isset($fields)) {
            wp_die('Invalid settings fields!', 400);
        }

        switch ($submitType) {
            case 'save':
                $input = $_POST[$this->topName];
                break;
            case 'reset':
                $input = FormField::extractDefaultValue($fields);
                break;
            default:
                wp_die('Invalid Form Submission!', 400);
                break;
        }

        $optionBasename = $this->getOptionBasename($tabId);
        $validator = FormField::createValidator($fields, $this->getData($optionBasename));
        $result = $validator->safeParse($input);
        // dump($input, $result['error']);
        if (!$result['success']) {
            return $this->ctx->json($result['error']->format(), 400);
        }
        $saveData = $result['data'];
        $saved = $this->save($optionBasename, $saveData);
        $this->ctx->json([
            'success' => true,
            'message' => $saved ? __('Settings saved.') : __('No change or failed!'),
        ]);
    }

    public function save(string $basename, $validated)
    {
        return $this->ctx->option->update($basename, $validated);
    }

    public function getData(string $basename, $default = [])
    {
        return $this->ctx->option->array($basename, $default);
    }

    public function render()
    {
        $data = $this->getPageData();
        echo '<div class="wrap" id="root">';
        echo '<script type="application/json">'.json_encode($data).'</script>';
        echo '</div>';
    }

    protected function getPageData()
    {
        return [
            'title'    => get_admin_page_title(),
            'form'     => $this->toClientSettingsForm(),
            '_wpnonce' => $this->generateToken(),
            'locale'   => FormField::getLocaleText(),
        ];
    }

    protected function generateToken()
    {
        return wp_create_nonce($this->get('settings', 'admin_option_page_form'));
    }

    protected function checkToken()
    {
        return check_ajax_referer($this->get('settings', 'admin_option_page_form'), false, false);
    }

    public function toClientSettingsForm()
    {
        $settings = $this->getSettings();
        if (isset($settings['tabs'])) {
            foreach ($settings['tabs'] as &$tab) {
                if (empty($tab['fields'])) {
                    $tab['fields'] = [];
                } else {
                    $data = $this->getData($this->getOptionBasename($tab['id']), []);
                    $tab['fields'] = FormField::toClientFields($tab['fields'], $data, $this->topName);
                }
            }
            if (isset($settings['active_tab'])) {
                $settings['activeTab'] = $settings['active_tab'];
                unset($settings['active_tab']);
            }
        } else {
            $settings = array_key_exists('id', $settings) ? $settings['fields'] ?? [] : $settings;
            $data = $this->getData($this->getOptionBasename(), []);
            $settings = FormField::toClientFields($settings, $data, $this->topName);
        }
        return $settings;
    }

    public function getTopSettingsFields($tabId = null)
    {
        $data = $this->getSettings();
        if (isset($data['tabs'])) {
            $tab = $this->getTab($tabId);
            return isset($tab,$tab['fields']) ? $tab['fields'] : null;
        } elseif (array_key_exists('id', $data)) {
            return $data['fields'] ?? null;
        }
        return $data;
    }

    public function hasTabs()
    {
        return isset($this->getSettingsRaw()['tabs']);
    }

    public function getTab($tabId = null)
    {
        $settings = $this->getSettings();
        if (isset($settings['tabs'])) {
            foreach ($settings['tabs'] as $tab) {
                if ($tab['id'] === $tabId) {
                    return $tab;
                }
            }
        }
        return null;
    }

    public function getSettingsKey()
    {
        return (string) $this->get('settings', '');
    }

    public function getSettings()
    {
        return $this->settings ??= $this->parseSettings($this->getSettingsRaw());
    }

    public function getSettingsRaw()
    {
        return $this->settingsRaw ??= $this->ctx->config->array($this->getSettingsKey());
    }

    /**
     * @param  SettingsRaw $settings
     * @return Settings
     */
    protected function parseSettings(array $settings)
    {
        if (isset($settings['tabs'])) {
            $this->parseSettingsSubFile($settings['tabs']);
        } else {
            $this->parseSettingsSubFile($settings);
        }
        return $settings;
    }

    protected function parseSettingsSubFile(&$fields)
    {
        foreach ($fields as &$tab) {
            if (isset($tab['fields']) && is_string($tab['fields'])) {
                $tab['fields'] = $this->ctx->config->array($tab['fields'], []);
            }
        }
    }

    /**
     * 解析规则：
     * - 如果加载的配置数据，顶级有id则使用此id，其他情况自动生成
     * - 从setting$settingsKey自动生成：去掉`admin/settings`前缀，去掉`/index`后缀，斜杠转下划线
     * - 如果是Tabs结构，每个Tab独立一个option，上述规则先生成基本`name`，再组合`name_tabid`.
     */
    public function getOptionBasename($tabId = null)
    {
        $settings = $this->getSettingsRaw();
        if (!empty($settings['id'])) {
            $name = (string) $settings['id'];
        } else {
            $this->keyOptionBasename ??= self::generateOptionBasename();
            $name = $this->keyOptionBasename;
            $name = $tabId ? $name.'_'.$tabId : $name;
        }
        return $name;
    }

    protected function generateOptionBasename()
    {
        $name = $this->getSettingsKey();
        if (false !== strpos($name, '/')) {
            $prefix = $this->optionBasenamePrefix;
            $suffix = $this->optionBasenameSuffix;
            if (0 === strpos($name, $prefix)) {
                $name = substr($name, strlen($prefix));
            }
            if (substr($name, -strlen($suffix)) === $suffix) {
                $name = substr($name, 0, -strlen($suffix));
            }
            $name = str_replace('/', '_', $name);
        }
        return $name;
    }
}
