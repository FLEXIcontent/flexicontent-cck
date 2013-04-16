/*
Input Mask plugin extensions
http://github.com/RobinHerbots/jquery.inputmask
Copyright (c) 2010 - 2013 Robin Herbots
Licensed under the MIT license (http://www.opensource.org/licenses/mit-license.php)
Version: 0.0.0

Optional extensions on the jquery.inputmask base
*/
(function ($) {
    //number aliases
    $.extend($.inputmask.defaults.aliases, {
        'decimal': {
            mask: "~",
            placeholder: "",
            repeat: 10,
            greedy: false,
            numericInput: true,
            digits: "*", //numer of digits
            groupSeparator: ",", // | "."
            radixPoint: ".",
            groupSize: 3,
            autoGroup: false,
            getMaskLength: function (buffer, greedy, repeat, currentBuffer, opts) { //custom getMaskLength to take the groupSeparator into account
                var calculatedLength = buffer.length;

                if (!greedy && repeat > 1) {
                    calculatedLength += (buffer.length * (repeat - 1));
                }

                var escapedGroupSeparator = $.inputmask.escapeRegex.call(this, opts.groupSeparator);
                var escapedRadixPoint = $.inputmask.escapeRegex.call(this, opts.radixPoint);
                var currentBufferStr = currentBuffer.join(''), strippedBufferStr = currentBufferStr.replace(new RegExp(escapedGroupSeparator, "g"), "").replace(new RegExp(escapedRadixPoint), ""),
                groupOffset = currentBufferStr.length - strippedBufferStr.length;
                return calculatedLength + groupOffset;
            },
            postFormat: function (buffer, pos, reformatOnly, opts) {
                var cbuf = buffer.slice();
                if (!reformatOnly) cbuf.splice(pos, 0, "?"); //set position indicator
                var bufVal = cbuf.join('');
                if (opts.autoGroup || (reformatOnly && bufVal.indexOf(opts.groupSeparator) != -1)) {
                    bufVal = bufVal.replace(new RegExp("\\" + opts.groupSeparator, "g"), '');
                    var radixSplit = bufVal.split(opts.radixPoint);
                    bufVal = radixSplit[0];
                    var reg = new RegExp('([-\+]?[\\d\?]+)([\\d\?]{' + opts.groupSize + '})');
                    while (reg.test(bufVal)) {
                        bufVal = bufVal.replace(reg, '$1' + opts.groupSeparator + '$2');
                        bufVal = bufVal.replace(opts.groupSeparator + opts.groupSeparator, opts.groupSeparator);
                    }
                    if (radixSplit.length > 1)
                        bufVal += opts.radixPoint + radixSplit[1];
                }
                buffer.length = bufVal.length; //align the length
                for (var i = 0, l = bufVal.length; i < l; i++) {
                    buffer[i] = bufVal.charAt(i);
                }
                var newPos = reformatOnly ? pos : $.inArray("?", buffer);
                if (!reformatOnly) buffer.splice(newPos, 1);

                return newPos;
            },
            regex: {
                number: function (groupSeparator, groupSize, radixPoint, digits) {
                    var escapedGroupSeparator = $.inputmask.escapeRegex.call(this, groupSeparator);
                    var escapedRadixPoint = $.inputmask.escapeRegex.call(this, radixPoint);
                    var digitExpression = isNaN(digits) ? digits : '{0,' + digits + '}';
                    return new RegExp("^[\+-]?(\\d+|\\d{1," + groupSize + "}((" + escapedGroupSeparator + "\\d{" + groupSize + "})?)+)(" + escapedRadixPoint + "\\d" + digitExpression + ")?$");
                }
            },
            onKeyDown: function (e, buffer, opts) {
                var $input = $(this), input = this;
                if (e.keyCode == opts.keyCode.TAB) {
                    var radixPosition = $.inArray(opts.radixPoint, buffer);
                    if (radixPosition != -1) {
                        var masksets = $input.data('inputmask')['masksets'];
                        var activeMasksetIndex = $input.data('inputmask')['activeMasksetIndex'];
                        for (var i = 1; i <= opts.digits && i < opts.getMaskLength(masksets[activeMasksetIndex]["_buffer"], masksets[activeMasksetIndex]["greedy"], masksets[activeMasksetIndex]["repeat"], buffer, opts) ; i++) {
                            if (buffer[radixPosition + i] == undefined) buffer[radixPosition + i] = "0";
                        }
                        input._valueSet(buffer.join(''));
                    }
                } else if (e.keyCode == opts.keyCode.DELETE || e.keyCode == opts.keyCode.BACKSPACE) {
                    opts.postFormat(buffer, 0, true, opts);
                    input._valueSet(buffer.join(''));
                }
            },
            definitions: {
                '~': { //real number
                    validator: function (chrs, buffer, pos, strict, opts) {
                        if (chrs == "") return false;
                        if (pos == 1 && buffer[0] === '0' && new RegExp("[\\d-]").test(chrs)) { //handle first char
                            buffer[0] = "";
                            return { "pos": 0 };
                        }

                        var cbuf = strict ? buffer.slice(0, pos) : buffer.slice();

                        cbuf.splice(pos + 1, 0, chrs);
                        var bufferStr = cbuf.join('');
                        if (opts.autoGroup && !strict) //strip groupseparator
                            bufferStr = bufferStr.replace(new RegExp("\\" + opts.groupSeparator, "g"), '');
                        var isValid = opts.regex.number(opts.groupSeparator, opts.groupSize, opts.radixPoint, opts.digits).test(bufferStr);
                        if (!isValid) {
                            //let's help the regex a bit
                            bufferStr += "0";
                            isValid = opts.regex.number(opts.groupSeparator, opts.groupSize, opts.radixPoint, opts.digits).test(bufferStr);
                            if (!isValid) {
                                //make a valid group
                                var lastGroupSeparator = bufferStr.lastIndexOf(opts.groupSeparator);
                                for (i = bufferStr.length - lastGroupSeparator; i <= 3; i++) {
                                    bufferStr += "0";
                                }

                                isValid = opts.regex.number(opts.groupSeparator, opts.groupSize, opts.radixPoint, opts.digits).test(bufferStr);
                                if (!isValid && !strict) {
                                    if (chrs == opts.radixPoint) {
                                        isValid = opts.regex.number(opts.groupSeparator, opts.groupSize, opts.radixPoint, opts.digits).test("0" + bufferStr + "0");
                                        if (isValid) {
                                            buffer[pos] = "0";
                                            pos++;
                                            return { "pos": pos };
                                        }
                                    }
                                }
                            }
                        }

                        if (isValid != false && !strict && chrs != opts.radixPoint) {
                            var newPos = opts.postFormat(buffer, pos + 1, false, opts);
                            return { "pos": newPos };
                        }
                        return isValid;
                    },
                    cardinality: 1,
                    prevalidator: null
                }
            },
            insertMode: true,
            autoUnmask: false
        },
        'non-negative-decimal': {
            regex: {
                number: function (groupSeparator, groupSize, radixPoint, digits) {
                    var escapedGroupSeparator = $.inputmask.escapeRegex.call(this, groupSeparator);
                    var escapedRadixPoint = $.inputmask.escapeRegex.call(this, radixPoint);
                    var digitExpression = isNaN(digits) ? digits : '{0,' + digits + '}'
                    return new RegExp("^[\+]?(\\d+|\\d{1," + groupSize + "}((" + escapedGroupSeparator + "\\d{" + groupSize + "})?)+)(" + escapedRadixPoint + "\\d" + digitExpression + ")?$");
                }
            },
            alias: "decimal"
        },
        'integer': {
            regex: {
                number: function (groupSeparator, groupSize) {
                    var escapedGroupSeparator = $.inputmask.escapeRegex.call(this, groupSeparator);
                    return new RegExp("^[\+-]?(\\d+|\\d{1," + groupSize + "}((" + escapedGroupSeparator + "\\d{" + groupSize + "})?)+)$");
                }
            },
            alias: "decimal"
        }
    });
})(jQuery);
