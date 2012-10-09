var canvas,
	shapes = [],
	mode = "",
	currentPoly,
	lastPoints,
	lastPos;

$(window).load(function()
{
	$('#canvas').attr('width',$('.fullImage').width());
	$('#canvas').attr('height',$('.fullImage').height());
	var global = this;
	canvas = global.canvas = new fabric.Canvas('canvas', { selection:false });

	$('#button-add').click(function()
	{
		mode = "polygon";
		deselect();
	});	
	
	$('#button-finish').click(function(e)
	{
		mode = "polygon";
		currentPoly.selectable = true;
		activeItem = currentPoly;
		currentPoly = null;
		canvas.setActiveObject(activeItem);
		mode = "";

		$(e.currentTarget).fadeOut('fast');
	});	
	
	$('#button-delete').click(function(e)
	{
		var myObj = canvas.getActiveObject();
		shapes = _.without(shapes,myObj);
		canvas.remove(myObj);
		deselect();
		
		$(e.currentTarget).fadeOut('fast');
	});
	
	// Retreive all registered shapes
	_.each(mapItems, function(a) {
		var obj = { 
		  left:		a.left, 
		  top:		a.top, 
		  fill:		a.fill, 
		  scaleX:	a.scaleX,
		  scaleY:	a.scaleY,
		  opacity:	0.7
		}
		
		var shape = new fabric.Polygon(a.points,obj);
		shape.lockRotation = true;
		shape.link = a.link;
		shape.title = a.title;
		canvas.add(shape);
		shapes.push(shape);
	});
	
	function add(left, top)
	{
		if (mode.length > 0) {
			var obj = { 
			  left: left, 
			  top: top, 
			  fill: '#' + getRandomColor(), 
			  opacity: 0.7
			};
			
			var shape;
			$('#button-finish').fadeIn('fast');
			obj.selectable = false;
			
			if (!currentPoly) {
				shape = new fabric.Polygon([{ x: 0, y: 0 }],obj);
				lastPoints = [{ x: 0, y: 0 }];
				lastPos = {left: left, top: top};
			}
			else {
				obj.left = lastPos.left;
				obj.top = lastPos.top;
				obj.fill = currentPoly.fill;
				currentPoly.points.push({ x: left - lastPos.left, y: top - lastPos.top });
				shapes = _.without(shapes,currentPoly);
				lastPoints.push({ x: left - lastPos.left, y: top - lastPos.top })
				shape = repositionPointsPolygon(lastPoints, obj);
				canvas.remove(currentPoly);
			}
			
			currentPoly = shape;

			shape.lockRotation =	true;
			shape.link =			$('#hrefBox').val();
			shape.title =			$('#titleBox').val();
			
			canvas.add(shape);
			shapes.push(shape);
		}
		else {
			deselect();
		}
	}
	
	var activeItem,
		activeEditCircles = {};
		
	canvas.observe('mouse:down', function(e)
	{
		if (!e.memo.target) {
			add(e.memo.e.layerX,e.memo.e.layerY);
		}
		else {
			if (_.detect(shapes, function(a) { return _.isEqual(a,e.memo.target) })) {
				if (!_.isEqual(activeItem, e.memo.target)) {
					clearEditControls();
				}
				
				activeItem = e.memo.target;
				if (activeItem.type == "polygon") {
					addEditCircles();
				}
			}
		}
	});
	
	canvas.observe('object:moving', function(e)
	{
		readjustControls(e);
	});
	
	canvas.observe('mouse:up', function(e)
	{
		if (!_.isUndefined(activeItem) && !_.isNull(activeItem)) {
			if (activeItem.type == "polygon") {
				$('#button-delete').fadeIn('fast');
				if (activeEditCircles.length == 0) {
					addEditCircles();
				}
			}
		}
		else {
			$('#button-delete').fadeOut('fast');
		}
	});
	
	canvas.observe('object:modified', function(e)
	{
		if (activeItem.type == "polygon") {
			shapes = _.without(shapes,activeItem);
			canvas.remove(activeItem);
			
			var obj = { 
			  left:		activeItem.left, 
			  top:		activeItem.top, 
			  fill:		activeItem.fill, 
			  scaleX:	activeItem.scaleX,
			  scaleY:	activeItem.scaleY,
			  opacity:	0.7,
			  link:		activeItem.link,
			  title:	activeItem.title
			}
			
			activeItem = repositionPointsPolygon(activeItem.points,obj);
			activeItem.link = obj.link;
			activeItem.title = obj.title;
			activeItem.lockRotation = true;
			
			canvas.add(activeItem);
			shapes.push(activeItem);
			clearEditControls(e);
		}
	});
	
	function repositionPointsPolygon(lastPoints, obj)
	{
		quickshape = new fabric.Polygon(lastPoints,obj);
		minX = _.min(lastPoints, function(a) { return a.x }).x;
		minY = _.min(lastPoints, function(a) { return a.y }).y;
		
		var newpoints = [];
		_.each(lastPoints, function(a)
		{
			var newPoint = {};
			newPoint.x = a.x - (quickshape.width/2) - minX;
			newPoint.y = a.y - (quickshape.height/2) - minY;
			newpoints.push(newPoint);
		});
		
		obj.left += (quickshape.width/2 + minX)*quickshape.scaleX;
		obj.top += (quickshape.height/2 + minY)*quickshape.scaleY;
		
		return new fabric.Polygon(newpoints,obj);
	}
	
	function deselect()
	{
		if (!_.isUndefined(activeItem) && !_.isNull(activeItem)) {
			activeItem.setActive(false);
			activeItem = null;
			
			clearEditControls();
		}
	}
	
	function readjustControls(e)
	{
		if (typeof e.memo.target == "object") {
			tgt = e.memo.target;
			
			if (_.detect(activeEditCircles, function(a) { return _.isEqual(a,tgt) })) {
				activeItem.points[tgt.pointIndex].x = (tgt.left - activeItem.left) / activeItem.scaleX;
				activeItem.points[tgt.pointIndex].y = (tgt.top - activeItem.top) / activeItem.scaleY;
			}
			else {
				if (activeItem.type == "polygon") {
					_.each(activeEditCircles,function(p)
					{
						p.left = activeItem.left + (activeItem.points[p.pointIndex].x * activeItem.scaleX);
						p.top = activeItem.top + (activeItem.points[p.pointIndex].y * activeItem.scaleY);
					});
				}
			}
		}
	}
	
	function clearEditControls()
	{
		_.each(activeEditCircles,function (item) { canvas.remove(item); });
		activeEditCircles = [];
	}
	
	function addEditCircles()
	{
		_.each(activeItem.points,function(p, i)
		{
			var holdershape = new fabric.Circle({
				left:			activeItem.left + (p.x * activeItem.scaleX),
				top:			activeItem.top + (p.y * activeItem.scaleY),
				strokeWidth:	3,
				radius:			5,
				fill:			'#fff',
				stroke:			'#666'
			});
			
			holdershape.hasControls = holdershape.hasBorders = false;
			holdershape.pointIndex = i;
			
			activeEditCircles.push(holdershape);
			canvas.add(holdershape);
		});
	}
	
	function getRandomColor()
	{
		return (
			pad(getRandomInt(0, 255).toString(16), 2) + 
			pad(getRandomInt(0, 255).toString(16), 2) + 
			pad(getRandomInt(0, 255).toString(16), 2)
		);
	}
	  
	function pad(str, length)
	{
		while (str.length < length) {
			str = '0' + str;
		}
		
		return str;
	};

	var getRandomInt = fabric.util.getRandomInt;
		
	$('form').submit(function(e)
	{
		var areas = [];
		
		_.each(shapes,function(a)
		{
			var area = {};
			area.link = a.link;
			area.title = a.title;
			if (a.type == 'polygon') {
					var coords = [],
						jsCoords = [],
						i = 0;
						
					area.shape = "poly";
					_.each(a.points, function(p)
					{
						newX = (p.x * a.scaleX) + a.left;
						newY = (p.y * a.scaleY) + a.top;
						
						coords.push(newX);
						coords.push(newY);
						
						jsCoords[i] = [];
						jsCoords[i]['x'] = newX - a.left;
						jsCoords[i]['y'] = newY - a.top;
						i++;
					});
					
					area.coords =	coords;
					area.fill =		a.fill;
					area.left =		a.left;
					area.top =		a.top;
					
					/*
					// Debug only :
					console.log(a);
					console.log(jsCoords);
					*/
					area.jsCoords = jsCoords;
					
			}
			
			areas.push(area);
		});
		
		// Templating
		$('#code-container').html(_.template($('#map_template').html(), { areas:areas }));
		
		return false;
	});
});