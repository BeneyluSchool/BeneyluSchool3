var gToken = "";
var ignorer = "";
var relanceAutomatique = true;
var color = "";
var apikey = "b9e47ecbdc1668bec6e2597be2546e3e03fc8637"; // Beneylu
var pathPlugin = "/ent/medias/js/cordial";

function setCookie(sName, sValue) {
    document.cookie = sName + "=" + encodeURIComponent(sValue);
}

function replaceAll(find, replace, str) {
    while (str.indexOf(find) > -1) {
        str = str.replace(find, replace);
    }
    return str;
}

function getCookieVal(offset) {
    var endstr = document.cookie.indexOf(";", offset);
    if (endstr == -1)
        endstr = document.cookie.length;
    return unescape(document.cookie.substring(offset, endstr));
}

function GetCookie(name) {
    var arg = name + "=";
    var alen = arg.length;
    var clen = document.cookie.length;
    var i = 0;
    while (i < clen) {
        var j = i + alen;
        if (document.cookie.substring(i, j) == arg)
            return getCookieVal(j);
        i = document.cookie.indexOf(" ", i) + 1;
        if (i == 0) break;
    }
    return null;
}

function htmlDecode(value) {
    return $('<div/>').html(value).text();
}

function isInArray(value, array) {
    return array.indexOf(value) > -1;
}

tinymce.PluginManager.add('cordial', function(editor, url) {
    var PluginManager = tinymce.PluginManager;
    var Menu = tinymce.ui.Menu;
    var DOMUtils = tinymce.dom.DOMUtils;
    var JSONRequest = tinymce.util.JSONRequest;
    //tinymce.PluginManager.load('contextmenu', "../tinymce/plugins/cordial/os/contextmenu.js");


    var ignorecache = [];
    var suggestionsMenu = null;
    var suggestionscache = [];
    var Tools = tinymce.util.Tools;

    function cleanQuotes(word) {
        return word.replace(/[\u2018\u2019]/g, "'");
    }

    function shiftFrom(fromNode, fromOffset, shiftOffset, stopNode) {
        ////console.log("shift : from "+fromNode+" ("+fromOffset+"), of "+shiftOffset);

        if (fromNode.nodeName.toLowerCase() != "#text" || fromNode.textContent.length == 0) {
            fromNode = nextTextNode(fromNode, stopNode);
        }

        gResNode = null;
        gResOffset = 0;

        if (fromNode == null) {
            return;
        }

        var currNode = fromNode;
        ////console.log("We are there : "+fromNode.textContent);
        var len = currNode.textContent.length - fromOffset;

        while (len < shiftOffset && currNode != null) {
            ////console.log("len :"+len+" < shiftOffset :"+shiftOffset+" ET currNode != null : "+currNode);
            var nextNode = nextTextNode(currNode, stopNode);
            if (nextNode != null) {
                ////console.log("We go there : "+nextNode.textContent);
                currNode = nextNode;
                len += currNode.textContent.length;
            } else {
                ////console.log((shiftOffset-len)+" char missing.");
                len = shiftOffset;
            }
        }

        if (currNode != null) {
            gResNode = currNode;
            gResOffset = currNode.textContent.length - (len - shiftOffset);

            // si on est en fin de node et que le node suivant existe, on saute
            if (gResOffset == currNode.textContent.length) {
                ////console.log("Fin de node " + currNode);
                var nextNode = nextTextNode(currNode, stopNode);
                if (nextNode != null) {
                    ////console.log("set on " + nextNode);
                    gResNode = nextNode;
                    gResOffset = 0;
                }
            }
        }
    }

    function unwrapInit(node) {
        var text = node.innerText || node.textContent;

        if (isIE()) {
            text = text.replace(/  /g, " " + String.fromCharCode(160));
        }
        node.outerHTML = node.innerHTML;
        var parent = node.parentNode;
        var child = node.firstChild;

        /*while (child.nextSibling != null)
        {
        	if (child.nodeType == 3 && child.nextSibling.nodeType == 3)
        	{
        		child.innerText = child.innerText + child.nextSibling.innerText;
        		parent.removeChild(child.nextSibling);
        	}
        	child = child.nextSibling;
        }*/
        //var content = editor.getDoc().createTextNode(text);
        //node.parentNode.insertBefore(content, node);
        //node.parentNode.removeChild(node);

        // get the element's parent node
        var parent = node.parentNode;
        if (parent != null) {
            // move all children out of the element
            while (node.firstChild) parent.insertBefore(node.firstChild, node);

            // remove the empty element
            parent.removeChild(node);
        }

    }


    function jumpIt(node) {
        return false;
    }

    function stopSpelling(node) {
        if (node.attributes != null) {
            if (node.attributes.getNamedItem("id") != null) {
                if (node.attributes.getNamedItem("id").value == "ecxstopSpelling") // Hotmail
                {
                    return true;
                }
            }
            if (node.attributes.getNamedItem("size") != null) {
                if (node.nodeName.toLowerCase() == "hr" && node.attributes.getNamedItem("size").value == "1") // Yahoo mail
                {
                    return true;
                }
            }
        }

        return false;
    }

    function nextTextNode(node, stopNode) {
        ////console.log("nextNode of "+node);

        if (node == null || stopNode == null || stopSpelling(node)) {
            return null;
        }

        var nextNode = node.firstChild;

        if (nextNode == null || node.nodeName.toLowerCase() == "style" ||
            jumpIt(node)) {
            nextNode = node.nextSibling;
            if (nextNode == null) {
                nextNode = node.parentNode;
                if (nextNode == null) {

                    return null;
                } else if (nextNode == stopNode) {
                    return null;
                } else {
                    while (nextNode != null && nextNode != stopNode && nextNode.nextSibling == null) {
                        nextNode = nextNode.parentNode;
                    }

                    if (nextNode == null || nextNode == stopNode) {
                        ////console.log("no other node");
                        return null;
                    }

                    nextNode = nextNode.nextSibling;
                }
            }
        }

        if (nextNode.nodeName.toLowerCase() == "#text" && nextNode.textContent.length > 0) {
            //console.log("found : "+nextNode.textContent);
            return nextNode;
        } else {
            return nextTextNode(nextNode, stopNode);
        }
    }

    function getTextWithoutStyle(body) {
        var nextNode = nextTextNode(body, body);
        var res = "";
        while (nextNode != null) {
            /*if (nextNode.parentNode.localName != "b" || nextNode.parentNode.localName !="i" || nextNode.parentNode.localName !="u" || nextNode.parentNode.localName !="span")
            	nextNode.textContent = nextNode.textContent+"\n";*/
            if ((nextNode.parentNode.localName == "h1" || nextNode.parentNode.localName == "h2" || nextNode.parentNode.localName == "h3" || nextNode.parentNode.localName == "h4" || nextNode.parentNode.localName == "h5" || nextNode.parentNode.localName == "h6" || nextNode.parentNode.localName == "li") && (!nextNode.parentNode.textContent.endsWith("\n"))) {
                nextNode.textContent = nextNode.textContent + "\n";
            }
            if (nextNode.parentNode.localName == "div" && (!nextNode.parentNode.textContent.endsWith("\n"))) {
                nextNode.textContent = nextNode.textContent + "\n";
            }

            if (nextNode.parentNode.localName == "a" && (!nextNode.parentNode.textContent.endsWith("\n"))) {
                nextNode.textContent = nextNode.textContent + "\n";
            }

            if (nextNode.parentNode.localName == "p" && (!nextNode.parentNode.textContent.endsWith("\n"))) {
                nextNode.textContent = nextNode.textContent + "\n";
            }

            res += nextNode.textContent;
            nextNode = nextTextNode(nextNode, body);
        }
        return res;


    }

    function getBody(docNode) {
        if (docNode == null) {
            return null;
        }
        var htmlNode = docNode.firstChild;
        //console.log(docNode);
        while (htmlNode != null) {
            if (htmlNode.nodeName.toLowerCase() == "html") {
                var children = htmlNode.childNodes;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (child != null && child.nodeName.toLowerCase() == "body") {
                        return child;
                    }
                }
            }
            htmlNode = htmlNode.nextSibling;
        }
        return null; // No body
    }


    function isIE() {
        var au = navigator.userAgent.toLowerCase();
        var found = (au.indexOf("msie") > -1 || au.indexOf("trident") > -1 || au.indexOf(".net clr") > -1)
        return found;
    }

    function placeSuggestion(suggestion, target) {
        /*console.log(tinyMCE.activeEditor);
        tinyMCE.activeEditor.insertContent(suggestion);*/

        var content = editor.getDoc().createTextNode(suggestion);
        target.parentNode.insertBefore(content, target);
        target.parentNode.removeChild(target);
    }

    function unwrap(node) {
        var text = node.innerText || node.textContent;
        if (isIE()) {
            text = text.replace(/  /g, " " + String.fromCharCode(160));
        }
        var content = editor.getDoc().createTextNode(text);
        node.parentNode.insertBefore(content, node);
        node.parentNode.removeChild(node);
    }

    function ignoreWord(target, word, all) {

        if (all) {
            var nameClass = target.className;

            Tools.each(editor.dom.select('span.' + nameClass), function(item) {
                var text = item.innerText || item.textContent;
                if (text == word) {
                    unwrap(item);
                }
            });
        } else {
            unwrap(target);
        }

        // gestion des ignorer
        ignorer = ignorer + word + "_" + codeErr + "|";

        if (relanceAutomatique) {
            // on relance la correction
            corrigerCkeditor('#' + editor.name, 'textareaCkeditor');
        }
    }

    function generateMemuItem(suggestion, target) {
        return {
            text: suggestion,
            image: pathPlugin + '/theme/picto_menu_contextuel_editer.png',
            disabled: false,
            icon: 'cordialModifier',
            onclick: function() {

                placeSuggestion(suggestion, target);
            }
        }
    }

    function getMenuItemsArray(target, word) {
        var contenuErreur = target.getAttribute('value');

        var correction = contenuErreur.split('\n');
        var message = correction[0];
        message = replaceAll("<b>", "", message);
        message = replaceAll("</b>", "", message);
        var alternatives = correction[1];

        var alternative = alternatives.split('|');

        var items = [];

        // si message contient "Espace superflu avant la ponctuation." -> word+"."
        for (var i = 0; i < alternative.length; i++) {

            if (alternative[i] != word && alternative[i] != "") {
                var suggestion = alternative[i];
                items.push(generateMemuItem(suggestion, target));
            }

        }

        items.push({
            text: message,
            image: pathPlugin + '/theme/picto_menu_contextuel_info.png',
            //icon: 'spellchecker',
            disabled: true
        });

        if (!items.length) {
            items.push({
                text: "(Pas de suggestions.)",
                disabled: true
            });
        }
        if (alternative && alternative.length == 2 && alternative[0].indexOf(String.fromCharCode(160)) > -1) { /**/ } else {
            items.push({
                text: '-'
            });
            items.push({
                text: 'Ignorer',
                image: pathPlugin + '/theme/picto_menu_contextuel_ignorer.png',
                onclick: function() {
                    unwrap(target);

                    //ignoreWord(target, word, true);
                    console.log(target.getAttribute('value').split('\n'));
                    //target.className = "";
                    //target.style.borderWidth = '0px';
                    //editor.insertText(suggestion);

                    var correction = target.getAttribute('value').split('\n');
                    var codeErr = correction[2];
                    //console.log(correction);
                    var phraseInitiale = correction[3];
                    var phraseModifie = "";

                    if (ignorer.search(phraseInitiale + "_") != -1) {
                        phraseModifie = phraseInitiale;
                    }


                    var alternatives = correction[1];

                    var alternative = alternatives.split('|');

                    var start = correction[4];
                    var end = correction[5];

                    var prefixSentence = phraseInitiale.substring(0, start);
                    var suffixSentence = phraseInitiale.substring(end);

                    var nouvellePhraseAModifier = prefixSentence + suggestion + suffixSentence;
                    // gestion des ignorer
                    if (ignorer != "") {
                        var tabIgnorer = ignorer.split('|');

                        if (!isInArray(word + "_" + codeErr, tabIgnorer)) {
                            ignorer = ignorer + word + "_" + codeErr + "|";
                        }

                    } else {
                        ignorer = ignorer + word + "_" + codeErr + "|";
                    }

                }
            })
        }
        return items;
    }

    function showSuggestionsMenu(e, target, word) {
        //console.log(target);

        var items = getMenuItemsArray(target, word);


        //console.log(items);
        if (editor.plugins.contextmenu) {
            editor.rendercontextmenu(e, items)
            return;
        }

        suggestionsMenu = new Menu({
            items: items,
            context: 'contextmenu',
            onautohide: function(e) {
                typeElement = e.target.className.substr(0, 4);

                if (typeElement !== "span") {
                    e.preventDefault();
                }
            },
            onhide: function() {
                suggestionsMenu.remove();
                suggestionsMenu = null;
            }
        });

        suggestionsMenu.renderTo(document.body);
        var pos = DOMUtils.DOM.getPos(editor.getContentAreaContainer());
        var targetPos = editor.dom.getPos(target);
        var doc = editor.getDoc().documentElement;
        if (editor.inline) {
            pos.x += targetPos.x;
            pos.y += targetPos.y;
        } else {
            var scroll_left = (editor.getWin().pageXOffset || doc.scrollLeft) - (doc.clientLeft || 0);
            var scroll_top = (editor.getWin().pageYOffset || doc.scrollTop) - (doc.clientTop || 0);
            pos.x += targetPos.x - scroll_left;
            pos.y += targetPos.y - scroll_top;
        }
        suggestionsMenu.moveTo(pos.x, pos.y + target.offsetHeight);

    }


    editor.on('contextmenu', function(e) {
        //on prend les 4 premiers caractére de className
        typeElement = e.target.className.substr(0, 4);

        if (typeElement == "span") {

            e.preventDefault();
            e.stopPropagation();
            var rng = editor.dom.createRng();
            rng.setStart(e.target.firstChild, 0);
            rng.setEnd(e.target.lastChild, e.target.lastChild.length);
            editor.selection.setRng(rng);

            showSuggestionsMenu(e, e.target, rng.toString());
        } else {
            if (editor.rendercontextmenu) {
                editor.rendercontextmenu(e, false)
            }
        }
    });

    editor.on('keydown keypress', function(e) {
        editorHasFocus = true;
        //recheck after typing activity
        var target = editor.selection.getNode();

        //ignore navigation keys
        var ch8r = e.keyCode;
        if (ch8r >= 16 && ch8r <= 31) {
            return;
        }
        if (ch8r >= 37 && ch8r <= 40) {
            return;
        }
        //if user is typing on a typo remove its underline
        typeElement = target.className.substr(0, 4);

        if (typeElement == "span") {
            //console.log(target.style);
            target.style.borderWidth = '0px';
            target.className = '';
        }

    });

    function unWrapSynapseCorrecteur() {
        Tools.each(tinyMCE.activeEditor.dom.select('span.correctionCordial'), function(item) {
            unwrap(item);
        });
    }

    function launchCorrectionHtml(editor, node) {

        if (!String.prototype.endsWith) {

            String.prototype.endsWith = function(searchString, position) {
                var subjectString = this.toString();
                if (typeof position !== 'number' || !isFinite(position) || Math.floor(position) !== position || position > subjectString.length) {
                    position = subjectString.length;
                }
                position -= searchString.length;
                var lastIndex = subjectString.lastIndexOf(searchString, position);
                return lastIndex !== -1 && lastIndex === position;
            };
        }

        unWrapSynapseCorrecteur();


        var doc = tinyMCE.activeEditor.iframeElement.contentDocument;

        iframe = true;

        var bodyNode;

        if (node == null) {
            bodyNode = getBody(doc);
        } else {
            bodyNode = node;
        }
        parserDom(bodyNode);

        var tmp = doc.firstChild;

        if (bodyNode == null) {
            console.log("no body");
            return;
        }

        var fullText = getTextWithoutStyle(bodyNode);

        var currNode = nextTextNode(bodyNode, bodyNode);

        if (currNode == null) {
            // rien à corriger
            alert("Correction terminée."); // real alert !
        }
        var currOffset = 0;

        var rest = fullText;

        var status = 0;

        var token = GetCookie('SynapseCorrection');
        if (token == null) {
            token = gToken;
        }

        //fullText = fullText.replace(/&/ig, '&amp;').replace(/\'/ig, '&apos;').replace(/</ig, '&lt;').replace(/>/ig, '&gt;').replace(/"/ig, '&quot;')

        var text = fullText.replace(/&/ig, '&amp;').replace(/\'/ig, '&apos;').replace(/</ig, '&lt;').replace(/>/ig, '&gt;').replace(/"/ig, '&quot;');

        var xmlDocument = '<RequestDataSaas_Apikey><details>' + text + '</details><apikey>' + apikey + '</apikey><token>' + token + '</token></RequestDataSaas_Apikey>';

        $.ajax({
            type: "POST",

            url: "https://correction-synapse.azurewebsites.net/correctionCordialSaas",
            data: xmlDocument,
            dataType: 'xml',
            contentType: "application/xml",
            success: parseResponse,
            error: errorHandler
        });

        function parseResponse(responseData) {
            //on lit le token retour et on stocke dans le token
            var token = $(responseData).find('token').text();

            var erreurs = $(responseData).find('errors').text();
            if (erreurs == "") {
                //met à jour la valeur du cookies
                setCookie('SynapseCorrection', token);
                gToken = token;

                var xml = $(responseData).find('corrected').first().text();
                reply = $(xml);
                inputtextNew = '';

                var texteFinal = fullText;

                var sentenceNodes = reply.find('sentences').children('sentence');

                var iSentence = 0;

                var sentenceNodes = reply.find('sentences').children('sentence');

                if (sentenceNodes.length == 0) {
                    alert('Aucune faute d\351tect\351e par le correcteur cordial.');
                } else {
                    for (iSentence = sentenceNodes.length - 1; iSentence >= 0; iSentence--) {
                        var sent = sentenceNodes.eq(iSentence)
                        var startOffsetPhr = sent.attr("start");
                        var endOffsetPhr = parseInt(sent.attr("start")) + parseInt(sent.attr("length"));

                        var originalSentenceError = sent.find('inputText').text();

                        // on recupere les mots ou phrases ignorées
                        var tabIgnorer = ignorer.split('|');
                        i = 0;

                        var errorNodes = sent.find('errors').children();
                        for (i = errorNodes.length - 1; i >= 0; i--) {
                            var doc = tinyMCE.activeEditor.iframeElement.contentDocument;
                            iframe = true;

                            var bodyNode;

                            if (node == null) {
                                bodyNode = getBody(doc);
                            } else {
                                bodyNode = node;
                            }

                            parserDom(bodyNode);

                            shiftFrom(bodyNode, 0, startOffsetPhr, bodyNode);
                            var startSentenceNode = gResNode;
                            var startSentenceOffset = gResOffset;
                            shiftFrom(bodyNode, 0, endOffsetPhr, bodyNode);
                            var endSentenceNode = gResNode;
                            var endSentenceOffset = gResOffset;

                            var currentError = errorNodes.eq(i);
                            var type = currentError.attr("type");
                            if(type == undefined){
                                var type = currentError[0].attributes[1].value;
                            }
                            var start = currentError.attr("start");
                            var end = currentError.attr("end");
                            var proba = currentError.attr("proba");
                            var message = currentError.find('message').html();
                            var code = currentError.attr('code_domaine_erreur');

                            var prefixSentence = texteFinal.substring(0, start);
                            var originalword = texteFinal.substring(start, end);
                            var suffixSentence = texteFinal.substring(end);
                            var correctword = "";
                            if (currentError.attr("substitution") != undefined) {
                                correctword = currentError.attr("substitution");
                            } else {
                                correctword = originalword;
                            }

                            x = 0;
                            alternative = new Array();

                            var alternatives = currentError.find('alternatives').children();

                            for (x; x < alternatives.length; x++) {
                                alternative += alternatives.eq(x).text() + "|";
                            }

                            if (type == "typo") {
                                color = "blue";
                            } else if (type == "spell") {
                                color = "red";
                            } else if (type == "grammar") {
                                color = "blue";
                            }

                            var message = replaceAll("[", "<", message);
                            message = replaceAll("]", ">", message);

                            idDiv = "faute" + iSentence;
                            idSpan = "span" + iSentence + i;

                            if (!isInArray(originalword + "_" + code, tabIgnorer)) {


                                shiftFrom(startSentenceNode, startSentenceOffset, start - startOffsetPhr, endSentenceNode);
                                var startErrorNode = gResNode;
                                var startErrorOffset = gResOffset;


                                shiftFrom(startSentenceNode, startSentenceOffset, end - startOffsetPhr, endSentenceNode);
                                var endErrorNode = gResNode;
                                var endErrorOffset = gResOffset;

                                var retourCorrection = message + "\n" + alternative + "\n" + code + "\n" + originalSentenceError + "\n" + startErrorOffset + "\n" + endErrorOffset;

                                if (code !== "19.18" && code !== "11.1" && code !== "50.52" && code !== "16.3" && apikey == "5R7nZKS5AX2WJEE8n5Lsb8nkf78bm78Ck4w3cdT7") {
                                    //supprimer les caractères erronés
                                    DeleteChars(startErrorNode, startErrorOffset, end - start, bodyNode);

                                    // on insère la correction à l'offset start
                                    InsertSpanNode(startErrorNode, startErrorOffset, originalword, retourCorrection, color, idSpan);
                                } else {
                                    //supprimer les caractères erronés
                                    DeleteChars(startErrorNode, startErrorOffset, end - start, bodyNode);

                                    // on insère la correction à l'offset start
                                    InsertSpanNode(startErrorNode, startErrorOffset, originalword, retourCorrection, color, idSpan);
                                }
                            }

                        }
                    }
                }

            }
        }

        function DeleteChars(startNode, startOffset, leftToDelete, stopNode) {
            var currentLength = startNode.textContent.length - startOffset;

            if (leftToDelete <= currentLength) // cas 1 : contenu à supprimer est dans le noeud en cours
            {
                startNode.textContent = startNode.textContent.substring(0, startOffset) + startNode.textContent.substring(startOffset + leftToDelete);
            } else // cas 2 : contenu à supprimer continue après le noeud en cours
            {
                startNode.textContent = startNode.textContent.substring(0, startOffset);
                leftToDelete = leftToDelete - currentLength;
                nextNode = nextTextNode(startNode, stopNode);
                DeleteChars(nextNode, 0, leftToDelete, stopNode);
            }
        }

        function InsertText(node, offset, textToInsert) {
            node.textContent = node.textContent.substring(0, offset) + textToInsert + node.textContent.substring(offset);
        }

        function InsertSpanNode(node, offset, displayText, retourCorrection, color, idSpan) {


            var sibling = node.nextSibling;

            var nodeBefore = document.createTextNode(node.textContent.substring(0, offset));
            var nodeAfter = document.createTextNode(node.textContent.substring(offset));

            var spanNode = document.createElement("span");
            spanNode.insertBefore(document.createTextNode(displayText), spanNode.firstChild);
            spanNode.setAttribute("class", idSpan + " correctionCordial");
            spanNode.setAttribute("id", idSpan);
            spanNode.setAttribute("value", retourCorrection);
            spanNode.setAttribute("style", "border-bottom: 1px double " + color + ";");

            if (node.parentElement != null) {

                node.parentElement.insertBefore(nodeBefore, node);
                node.parentElement.insertBefore(spanNode, node);
                node.parentElement.insertBefore(nodeAfter, node);
                node.parentElement.removeChild(node);

            }
        }


        function errorHandler(XHR, textStatus, errorThrown) {
            alert(textStatus);
        }
    }

    function parserDom(bodyNode) {
        var currentTextNode = nextTextNode(bodyNode, bodyNode);

        while (currentTextNode != null) {

            if (currentTextNode.textContent != undefined) {
                currentTextNode.nodeValue = currentTextNode.textContent.replace(/\u00a0/ig, ' ');
            }

            currentTextNode = nextTextNode(currentTextNode, bodyNode);
        }
    };

    // Add a button that opens a window
    editor.addButton('correction', {
        title: 'Corriger avec Cordial',
        image: pathPlugin + '/theme/cordialSaas.png',

        onclick: function(editor, node) {

            launchCorrectionHtml(editor, node);

        }
    });

    editor.addButton('correctionUnwrap', {
        title: 'Supprimer correction Cordial',
        image: pathPlugin + '/theme/cordialSaas.png',

        onclick: function() {

            unWrapSynapseCorrecteur();

        }
    });

});