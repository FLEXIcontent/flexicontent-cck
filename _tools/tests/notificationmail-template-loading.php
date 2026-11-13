<?php
/** Standalone API contract test: php _tools/tests/notificationmail-template-loading.php */
namespace Joomla\CMS\Mail {
    // Match the static loader used by Joomla 5.4 and 6.0. No loadTemplate method.
    class MailTemplate {
        public static $templates = array();
        public static $duringSend;
        public $key;
        public $data = array();
        public $rendered;
        public function __construct($key, $language = '') { $this->key = $key; }
        public static function getTemplate($key, $language) {
            return isset(self::$templates[$key]) ? clone self::$templates[$key] : null;
        }
        public function send() {
            if (self::$duringSend) { $callback = self::$duringSend; self::$duringSend = null; $callback(); }
            $mail = static::getTemplate($this->key, 'en-GB');
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
    same(true, FlexicontentHelperNotificationMail::canRenderConditions(), 'static API supported');
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
    same('INCLUDED ' . $first->data['title'], $first->rendered->body, 'outer context restored');
    MailTemplate::$duringSend = function() { throw new \RuntimeException('transport failed'); };
    try { $first->send(); throw new \LogicException('Expected exception'); }
    catch (\RuntimeException $e) { same('transport failed', $e->getMessage(), 'exception propagated'); }
    same(MailTemplate::$templates['test']->body, FlexicontentMailTemplate::getTemplate('test','')->body, 'no context leak after exception');
    $plain = new FlexicontentMailTemplate('test'); $plain->send();
    same(MailTemplate::$templates['test']->body, $plain->rendered->body, 'empty context unchanged');
    $missing = new FlexicontentMailTemplate('missing'); $missing->setConditionContext(array('catid'=>11));
    same(false, $missing->send(), 'missing template');
    MailTemplate::$templates['empty'] = (object) array('subject'=>'{if catid=99}Kept{/if}', 'body'=>'{if catid=99}Body{/if}', 'htmlbody'=>'');
    $empty = new FlexicontentMailTemplate('empty'); $empty->setConditionContext(array('catid'=>11)); $empty->send();
    same('Kept', $empty->rendered->subject, 'empty subject fallback');
    same(true, in_array('Empty rendered subject, original subject kept', $empty->getConditionErrors()), 'empty subject diagnostic');
    same(true, in_array('Empty rendered body', $empty->getConditionErrors()), 'empty body diagnostic');
    echo "$checks passed\n";
}
