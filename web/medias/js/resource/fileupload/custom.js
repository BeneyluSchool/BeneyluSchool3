
//Filename = nom du fichier
//Obliger de passer par cette fonction en JS (au lieu de twig) car appelé en js depuis script d'imprt de fichiers'
//with_image : si image affiche-t-on l'icône ?
function printImageFromFileName(fileName,with_image,size){
	
	var ext = fileName.split('.').pop();
	
	switch(ext.toLowerCase()){ 

		case 'jpg':
		case 'jpeg':
		case 'gif':		
		case 'png':	
			if(with_image == true)
				var type = "image";
			else
				return null;
		break;
		case 'avi':
		case 'mkv':
		case 'mp4':
		case 'mpeg':
		case 'mov':
			var type = "video";
		break;
		case "doc":
		case "docx":
		case "pdf":
			var type = "document";
		break;
		case "mp3":
		    var type = "audio";
		break;
		default:
			var type = "file";
		break;
	}
	
	return "<img src='/medias/images/resource/filetype/" + size + "/" + type + ".png' />";
	
}