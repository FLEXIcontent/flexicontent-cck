/** FLEXIcontent presets for the standalone Inputmask build. */
(() => {
  const Inputmask = window.Inputmask;
  if (!Inputmask) return;
  Inputmask.extendAliases({
    decimal: {
      alias: 'numeric',
      radixPoint: '.',
      groupSeparator: ',',
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    decimal_comma: {
      alias: 'numeric',
      radixPoint: ',',
      groupSeparator: '.',
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    currency: {
      alias: 'numeric',
      prefix: '$ ',
      groupSeparator: ',',
      digits: 2,
      digitsOptional: false,
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    currency_euro: {
      alias: 'currency',
      prefix: '\u20ac ',
      groupSeparator: ',',
      digits: 2,
      digitsOptional: false,
      clearMaskOnLostFocus: false,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    percentage_zero_nolimit: {
      alias: 'percentage',
      digits: 2,
      radixPoint: '.',
      min: 0,
      max: null,
      suffix: ' %',
      allowMinus: false,
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    percentage_nolimit_nolimit: {
      alias: 'percentage',
      digits: 2,
      radixPoint: '.',
      min: null,
      max: null,
      suffix: ' %',
      allowMinus: true,
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    integer: {
      alias: 'numeric',
      digits: 0,
      radixPoint: '',
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    unsigned: {
      alias: 'numeric',
      digits: 0,
      radixPoint: '',
      allowMinus: false,
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    },
    'mobile': {
      'mask': '9999 999 999',
      'autoUnmask': true,
      'insertMode': true,
      clearMaskOnLostFocus: true,
      removeMaskOnSubmit: true,
      unmaskAsNumber: false
    }
  });

  const formats = {
    datetime: 'dd/MM/yyyy HH:mm',
    datetime12: 'dd/MM/yyyy hh:mm tt',
    'hh:mm t': 'hh:mm tt',
    'h:s t': 'hh:ss tt',
    'hh:mm:ss': 'HH:mm:ss',
    'hh:mm': 'HH:mm',
    'mm/yyyy': 'MM/yyyy',
  };
  for (const date of ['dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy/mm/dd', 'dd.mm.yyyy', 'dd-mm-yyyy',
    'mm.dd.yyyy', 'mm-dd-yyyy', 'yyyy.mm.dd', 'yyyy-mm-dd']) formats[date] = date.replace('mm', 'MM');
  const legacyDates = Object.entries(formats);
  for (const [name, inputFormat] of legacyDates) {
    // "datetime" is the underlying 5.x alias. Preserve its default only for FLEXIcontent's legacy markup.
    if (name !== 'datetime') Inputmask.extendAliases({ [name]: { alias: 'datetime', inputFormat } });
  }

  window.fc_initInputMasks = (root = document) => {
    for (const input of root.querySelectorAll('input.has_inputmask, input.inputmask-regex')) {
      if (input.inputmask) continue;
      const regex = input.getAttribute('data-inputmask-regex');
      if (regex !== null) {
        Inputmask({ regex }).mask(input);
      } else if (/['"]alias['"]\s*:\s*['"]datetime['"]\s*(?:[,}]|$)/u.test(input.getAttribute('data-inputmask') || '')) {
        Inputmask({ alias: 'datetime', inputFormat: formats.datetime }).mask(input);
      } else {
        Inputmask().mask(input);
      }
    }
  };
  FlexiForm.ready(() => window.fc_initInputMasks());
})();
