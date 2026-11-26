<?php
/** Standalone API contract test: php _tools/tests/notificationmail-template-loading-j6.php */
namespace Joomla\CMS\Mail {
    // Match Joomla 6.2: send() calls $this->loadTemplate(); static getTemplate() uses a plain MailTemplate.
    class MailTemplate {
        public static $templates = array();
        public static $duringSend;
        protected $template_id;
        public $data = array();
        public $rendered;
        public function __construct($templateId, $language = '') { $this->template_id = $templateId; }
        protected function loadTemplate(string $key, string $language): ?\stdClass {
            return isset(self::$templates[$key]) ? clone self::$templates[$key] : null;
        }
        public static function getTemplate($key, $language) {
            return (new self($key, $language))->loadTemplate($key, $language);
        }
        public function send() {
            if (self::$duringSend) { $callback = self::$duringSend; self::$duringSend = null; $callback(); }
            $mail = $this->loadTemplate($this->template_id, 'en-GB');
            if (!$mail) return false;
            foreach (array('subject','body','htmlbody') as $property) {
                foreach ($this->data as $key=>$value) $mail->$property = str_replace('{' . strtoupper($key) . '}', $value, $mail->$property);
            }
            $this->rendered = $mail;
            return true;
        }
    }
}
namespace {
    define('_JEXEC', 1);
    require __DIR__ . '/../../admin/helpers/notificationmail.php';
    require __DIR__ . '/../../admin/helpers/notificationmailtemplate.php';
    use Joomla\CMS\Mail\MailTemplate;
    $checks = 0;
    function same($expected, $actual, $label) {
        global $checks; $checks++;
        if ($expected !== $actual) throw new \RuntimeException($label . ': ' . var_export($actual, true));
    }
    MailTemplate::$templates['test'] = (object) array(
        'subject'=>'Subject {if catid=11}YES{else}NO{/if} {TITLE}',
        'body'=>'{if catid=11}INCLUDED{else}EXCLUDED{/if} {TITLE}',
        'htmlbody'=>'<p>{if catid=11}INCLUDED{else}EXCLUDED{/if} {TITLE}</p>'
    );
    $original = serialize(MailTemplate::$templates);
    same(true, FlexicontentHelperNotificationMail::canRenderConditions(), 'loadTemplate API supported');
    $first = new FlexicontentMailTemplate('test'); $first->setConditionContext(array('catid'=>11));
    $first->data['title'] = '{if catid=99}literal user value{/if}';
    same(true, $first->send(), 'send');
    same('Subject YES ' . $first->data['title'], $first->rendered->subject, 'subject before tag substitution');
    same('INCLUDED ' . $first->data['title'], $first->rendered->body, 'plain body before tags');
    same('<p>INCLUDED ' . $first->data['title'] . '</p>', $first->rendered->htmlbody, 'HTML body before tags');
    same($original, serialize(MailTemplate::$templates), 'stored templates unchanged');
    $second = new FlexicontentMailTemplate('test'); $second->setConditionContext(array('catid'=>99));
    MailTemplate::$duringSend = function() use ($second) { $second->send(); };
    $first->send();
    same('EXCLUDED {TITLE}', $second->rendered->body, 'nested context');
    same('INCLUDED ' . $first->data['title'], $first->rendered->body, 'outer context kept');
    same(MailTemplate::$templates['test']->body, FlexicontentMailTemplate::getTemplate('test', '')->body, 'static loader returns the raw template');
    $plain = new FlexicontentMailTemplate('test'); $plain->send();
    same(MailTemplate::$templates['test']->body, $plain->rendered->body, 'empty context unchanged');
    $missing = new FlexicontentMailTemplate('missing'); $missing->setConditionContext(array('catid'=>11));
    same(false, $missing->send(), 'missing template');
    echo "$checks passed\n";
}
