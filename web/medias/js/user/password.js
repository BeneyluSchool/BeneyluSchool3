var types = {
		'consonant': null,
		'vowel': null,
		'number': []
	},
	usedLetters = [];
	step = 1,
	$mapLettersContainer = null;

$(function()
{
	// Confirm button
	$('#confirm_password').click(function (e)
	{
		if ($(e.currentTarget).hasClass('disabled')) {
			return false;
		}
		
		$('form').submit();
		
		return false;
	});
	
	// Showing the wheels
	$('.wheel-container').css('opacity', 1);

	$('.wheel.empty').maphilight();
	$('.wheel.vowel').maphilight({
		success: function ($div, $img, $canvas)
		{
			$mapLettersContainer = $div;

			// Events process for "OK" button, only for letters
			$('#confirm-button').mouseover(function () { $('.wheel.confirm .inner').addClass('hover'); });
			$('#confirm-button').mouseout(function () { $('.wheel.confirm .inner').removeClass('hover'); });
			$('#confirm-button').mousedown(function () { $('.wheel.confirm .inner').removeClass('hover').addClass('active'); });
			$('#confirm-button').mouseup(function () { $('.wheel.confirm .inner').removeClass('active').addClass('hover'); });
			$('#confirm-button').click(function ()
			{
				// Checking
				if (types['consonant'] == null || types['vowel'] == null) {
					return;
					// return displayError('Vous devez choisir une consonne et une voyelle !');
				}

				// Disable the OK button
				$('.wheel.confirm').addClass('disabled');
				$('#confirm-button').removeAttr('href', '#'); // cursor: default

				// Hide letters
				$('.letter').delay(50).fadeOut('slow');

				// Unlight all map area
				unlightMap();

				// Generate password
				setPassword(types['consonant'].data('letter') + types['vowel'].data('letter'));
				
				// Saving used letters and cleaning for next step
				usedLetters.push(types['consonant'].data('letter'));
				usedLetters.push(types['vowel'].data('letter'));
				types['consonant'] = null;
				types['vowel'] = null;
				step++;
				
				if (step == 4) {
					// Consignes
					var $rules = $('.content-message-password li');
					$rules.slideUp('fast');
					$($rules[1]).slideDown('fast');
					
					$('.wheel.numbers').toggleClass('rotating');
					setTimeout(function ()
					{
						showNumbers();
					}, 2400);
				}

				setTimeout(function ()
				{
					// Mark letter as used
					$.each($('.letter'), function (i, item)
					{
						var $item = $(item);
						for (i in usedLetters) {
							if (usedLetters[i] == $item.text()) {
								var $item = $('.map-area[data-letter="' + $item.text() + '"]'),
									itemData = $item.data('maphilight');

								$item.removeAttr('href');
								itemData.neverOn = true;
								$item.data('maphilight', itemData).trigger('alwaysOn.maphilight');
								$('#letter-' + $item.data('letter')).css('visibility', 'hidden');
							}
						}
					});

					$('.letter').fadeIn('slow');

					if (step < 4) {
						switchConfirm();
					}
				}, 2400);
				setTimeout(function ()
				{
					$('#wheel-letters').toggleClass('rotating');

					// Cheating
					switchConfirm();

					setTimeout(function ()
					{
						// If it's the number state, we show the number wheel during the rotate
						if (step == 4) {
							$('#wheel-letters').fadeOut('slow');
						}
					}, 500);
				}, 500);
			});

			// Click process letter/number
			$('.map-area').click(function (e)
			{
				var $this	= $(e.currentTarget),
					type	= $this.data('type'),
					data	= $this.data('maphilight');

				if (data.neverOn) {
					return false;
				}

				if (type != 'number') {
					if (types[type] != null) {
						var $item = types[type],
							itemData = $item.data('maphilight');

						// Switch OFF for the last choice
						itemData.fillOpacity = 0.20;
						itemData.alwaysOn = false;
						$item.data('maphilight', itemData).trigger('alwaysOn.maphilight');
					}

					// Switch ON for the new choice
					types[type] = $this;
					data.fillOpacity = 0.35;
					data.alwaysOn = true;
					$this.data('maphilight', data).trigger('alwaysOn.maphilight');
				}
				else {
					var number = $this.data('number'),
						found = false,
						newArray = [];

					// Search if user wants to switch off a number
					for (i in types['number']) {
						if (types['number'][i].data('number') == number) {
							delete types['number'][i];
							found = true;
						}
						else {
							newArray.push(types['number'][i]);
						}
					}

					types['number'] = newArray;

					if (found) {
						// Switch OFF current choice
						data.fillOpacity = 0.20;
						data.alwaysOn = false;
						$this.data('maphilight', data).trigger('alwaysOn.maphilight');

						// Disable the OK button
						$('.wheel.confirm').addClass('disabled');
						$('#confirm-button').removeAttr('href', '#'); // cursor: default
					}
					else if (types['number'].length < 3) {
						// Switch ON current choice
						data = $this.data('maphilight');
						data.fillOpacity = 0.35;
						data.alwaysOn = true;
						$this.data('maphilight', data).trigger('alwaysOn.maphilight');
						types['number'].push($this);
					}
				}

				// Enable the OK button
				if (types['consonant'] != null && types['vowel'] != null || types['number'].length == 3) {
					$('#confirm-button').attr('href', '#'); // cursor: pointer
					$('.wheel.confirm').removeClass('disabled');
				}
			});

			// Cheating, put the button under the mapping
			var $confirm = $('.wheel.confirm').clone();
			$confirm.css('display', 'block');
			$div.prepend($confirm);

			// Show letters
			showLetters($div);
		}
	});

	// Reset all wheels
	$('#clear_password').click(function (e)
	{
		var $this = $(e.currentTarget);

		if ($this.hasClass('disabled')) {
			return false;
		}

		$this.addClass('disabled').attr('disabled', 'disabled');

		var $doorContainer = $('.door-container');
		if ($doorContainer.css('display') == 'none') {
			switchDoors(false);
			setTimeout(function ()
			{
				resetWheels();
			}, 1000);
		}
		else {
			resetWheels();
		}

		return false;
	});

	// Confirm process
	$('.wheel.confirm').click(function (e)
	{
		if (step == 4) {
			if (types['number'].length < 3) {
				return;
				// return displayError('Veuillez choisir trois chiffres !');
			}

			var selectedNumbers = '';
			for (i in types['number']) {
				selectedNumbers += types['number'][i].data('number');
			}

			// Generate password
			setPassword(selectedNumbers);

			// Disable the OK button
			$('.wheel.confirm').addClass('disabled');
			$('#confirm-button').removeAttr('href', '#'); // cursor: default
			switchDoors(false);
			step++;
			
			// Show password print link
			$('.link-print').slideDown('fast');
			
			// Consignes
			var $rules = $('.content-message-password li');
			$rules.slideUp('fast');
			$($rules[2]).slideDown('fast');

			$('#confirm_password').removeClass('disabled').removeAttr('disabled');
		}
	});
});

/**
 * Reset all variables and animations
 */
function resetWheels()
{
	types = {
		'consonant': null,
		'vowel': null,
		'number': []
	},
	usedLetters = [];
	step = 1;

	unlightMap();
	$('#confirm_password').addClass('disabled');
	$('.door-container').toggleClass('rotating');
	$('.wheel.numbers, #wheel-letters').removeClass('rotating');
	$('.letter, .number').remove();
	$('#wheel-letters').css('display', 'block');
	setPassword('');
	
	// Show password print link
	$('.link-print').slideUp('fast');
	
	$.each($('.map-area'), function (i, item)
	{
		var $item = $(item),
			data = $item.data('maphilight');
			
		if (data.neverOn) {
			$item.attr('href', '#');
			data.neverOn = false;
			$item.data('maphilight', data).trigger('alwaysOn.maphilight');
		}
	});

	setTimeout(function ()
	{
		// Disable the OK button
		if ($('.wheel.confirm')[0].style.display == 'none') {
			switchConfirm();
		}
		$('.wheel.confirm').addClass('disabled');
		$('#confirm-button').removeAttr('href', '#'); // cursor: default
	}, 1500);

	setTimeout(function ()
	{
		switchDoors(true);
		setTimeout(function ()
		{
			showLetters($mapLettersContainer);
			$('#clear_password').removeClass('disabled').removeAttr('disabled', 'disabled');
		}, 1000);
	}, 4500);
}

/**
 * Set the password in the input & in the HUD display
 * 
 * @params string password
 */
function setPassword(password)
{
	var $passHud = $('#password-hud'),
	$passInput = $('#password_generation_form_password');
	
	// Clear password if sf error
	if (step == 1) {
		$passInput.val('');
	}

	if (password == '') {
		$passHud.text('');
		$passInput.val('');
	}
	else {
		$passHud.text($passHud.text() + password);
		$passInput.val($passInput.val() + password);
	}
}

/**
 * Show an alert with an error message
 *
 * @params string msg 
 */
var errorTimer = null;
function displayError(msg)
{
	clearTimeout(errorTimer);
	var $alert = $('.alert');
	$alert.text(msg);

	$alert.slideDown('fast');
	errorTimer = setTimeout(function ()
	{
		$('.alert').slideUp('fast');
	}, 10000);
}

/**
 * Unlight all map area in all wheels
 */
function unlightMap()
{
	// Unlight all buttons
	$.each($('.map-area'), function (i, item)
	{
		var $item = $(item),
			data = $item.data('maphilight');

		if (data.alwaysOn) {
			data.fillOpacity = 0.20;
			data.alwaysOn = false;
			$item.data('maphilight', data).trigger('alwaysOn.maphilight');
		}
	});
}

/**
 * Cheating, switch between map area confirm button (letters) & html confirm button (numbers)
 */
function switchConfirm()
{
	$.each($('.wheel.confirm'), function (i, item)
	{
		var $item = $(item);
		if ($item.css('display') == 'none') {
			$item.css('display', 'block');
		}
		else {
			$item.css('display', 'none');
		}
	});
}

/**
 * Show all letters
 *
 * @params HTMLDiv $div
 */
function showLetters($div)
{
	var vowelsMap = [
		{letter: 'a', left: 225, top: 93}, {letter: 'e', left: 269, top: 174}, {letter: 'i', left: 230, top: 258},
		{letter: 'o', left: 127, top: 255}, {letter: 'u', left: 83, top: 172}, {letter: 'y', left: 131, top: 91}
	],
		consonantsMap = [
		{letter: 'b', left: 178, top: 21}, {letter: 'c', left: 225, top: 27}, {letter: 'd', left: 266, top: 49}, {letter: 'f', left: 304, top: 83}, 
		{letter: 'g', left: 323, top: 122}, {letter: 'h', left: 332, top: 173}, {letter: 'j', left: 328, top: 218}, {letter: 'k', left: 304, top: 265}, 
		{letter: 'l', left: 274, top: 302}, {letter: 'm', left: 222, top: 321}, {letter: 'n', left: 177, top: 328}, {letter: 'p', left: 129, top: 320}, 
		{letter: 'q', left: 84, top: 298}, {letter: 'r', left: 54, top: 265}, {letter: 's', left: 32, top: 219}, {letter: 't', left: 27, top: 172}, 
		{letter: 'v', left: 33, top: 123}, {letter: 'w', left: 51, top: 81}, {letter: 'x', left: 90, top: 47}, {letter: 'z', left: 132, top: 27}
	];

	setTimeout(function ()
	{
		showLetter(0, vowelsMap, $div, true);
		showLetter(0, consonantsMap, $div, false); 
	}, 250);
}

/**
 * @params int i
 * @params array map
 * @params HTMLDiv $el
 * @params boolean isVowel
 */
function showLetter(i, map, $el, isVowel)
{
	$div = $('<div />').addClass('letter').attr('id', 'letter-' + map[i].letter).text(map[i].letter).css({
		top: map[i].top + 'px',
		left: map[i].left + 'px'
	});

	if (isVowel) {
		$div.addClass('big');
	}

	$el.prepend($div);
	$div.fadeIn('fast');

	if (i < map.length - 1) {
		setTimeout(function ()
		{
			showLetter(i+1, map, $el, isVowel);
		}, isVowel ? 385 : 100);
	}
}

/**
 * Show all numbers
 */
function showNumbers()
{
	var $wheel = $('div.wheel.empty');
	var numbersMap = [
		{left: 176, top: 22}, {left: 265, top: 53}, {left: 321, top: 128}, {left: 320, top: 222}, {left: 269, top: 298},
		{left: 178, top: 330}, {left: 86, top: 301}, {left: 32, top: 228}, {left: 29, top: 131}, {left: 84, top: 52}
	];

	showNumber(0, numbersMap, $wheel);
}

/**
 * @params int i
 * @params array map
 * @params HTMLDiv $el
 */
function showNumber(i, map, $el)
{
	$div = $('<div />').addClass('number').text(i).css({
		top: map[i].top + 'px',
		left: map[i].left + 'px',
		display: 'none'
	});
	$el.prepend($div);
	$div.fadeIn('fast');

	if (i < map.length - 1) {
		setTimeout(function ()
		{
			showNumber(i+1, map, $el);
		}, 200);
	}
}

/**
 * Close or open the door
 *
 * @params boolean isOpen True if you want to open doors, false otherwise
 */
function switchDoors(isOpen)
{
	if (isOpen) {
		if (isOpen) {
			$('.door').removeClass('closed');
		}
		else {
			$('.door').addClass('closed');
		}

		setTimeout(function ()
		{
			$('.door-container').css('display', isOpen ? 'none' : 'block');
		}, 1000);
	}
	else {
		$('.door-container').css('display', isOpen ? 'none' : 'block');
		setTimeout(function ()
		{
			if (isOpen) {
				$('.door').removeClass('closed');
			}
			else {
				$('.door').addClass('closed');
			}
		}, 50);
	}
}