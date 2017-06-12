(function (angular) {
'use strict';

angular.module('bns.circusBirthday.configs', [
  'ui.router',
  'bns.core.appStateProvider',

  'bns.circusBirthday.controllers',
])

  .config(CircusBirthdayThemeConfig)
  .config(CircusBirthdayStatesConfig)

;

function CircusBirthdayThemeConfig ($mdThemingProvider) {

  // register our custom theme
  $mdThemingProvider.definePalette('bns-orange', $mdThemingProvider.extendPalette('orange', {
    contrastDefaultColor: 'light',
    contrastLightColors: ['500', '600', '700', '800', '900'],
  }));
  $mdThemingProvider.theme('circus-birthday')
    .accentPalette('bns-orange', {
      'default': 'A200',
    })
  ;

}

function CircusBirthdayStatesConfig ($stateProvider, appStateProvider) {

  // create a basic root state, with custom theme enabled
  var rootState = appStateProvider.createRootState('circus-birthday', true);

  $stateProvider
    .state('app.circusBirthday', rootState)

    .state('app.circusBirthday.drawings', {
      url: '', // default state
      templateUrl: 'views/circus-birthday/drawings.html',
      controller: 'CircusBirthdayDrawings',
      controllerAs: 'ctrl',
      resolve: {
        hasAccessBack: ['Users', function (Users) {
          return Users.hasRight('CIRCUS_BIRTHDAY_ACTIVATION')
            .then(function yep () {
              return true;
            })
            .catch(function nope () {
              return false;
            })
          ;
        }],
        drawings: function () {
          return [
            {
              code: 'lion',
              label: 'Le lion et le cerceau en feu',
              description: 'Le roi des animaux entre en piste. Son rugissement fait sensation. Dessine le lion lors de son plus célèbre numéro.',
              difficulty: 3,
              video: 'https://www.youtube.com/embed/sWDcj7mfDVg?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/lion.pdf',
              steps: [
                'Fais un cercle pour la tête. Ajoute 2 petits ronds sur le dessus pour les oreilles. Au milieu du cercle de la tête, fait un trait avec un “U” en dessous pour la truffe. Ajoute 2 petits ponts à l’envers sous la truffe pour faire la bouche. Dessine 2 ronds avec un point dedans pour faire les yeux.',
                'Dessine des pics autour de la tête pour la crinière !',
                'Pour dessiner le corps, fais un pont pour le dos. Ajoute 4 rectangles pour les pattes, terminés par des formes d’oeuf. Une grande ligne joint le haut des pattes avant et arrière pour faire le ventre. Dessine 3 traits sur chaque oeuf pour faire les griffes.',
                'Dessine une ligne arrondie pour la queue. Au bout, ajoute une petite flamme pour les poils.',
                'Derrière le lion, dessine un grand cercle. Sous le cercle, dessine une tige. Sous la tige, ajoute un rectangle.',
                'Ajoute des pics autour du cerceau pour faire les flammes. Grrrrrrrrrrrrrrrr !',
              ],
            },
            {
              code: 'cake',
              label: 'Le gâteau délicieux',
              description: 'Pour ton anniversaire un grand pâtissier s’affaire ! Dessine une pièce montée qui grimpe jusqu’au plafond.',
              difficulty: 1,
              video: 'https://www.youtube.com/embed/aIDP0fHeiGw?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/gateau.pdf',
              steps: [
                'Dessine 3 rectangles : un grand en bas, un moyen au milieu, un petit sur le dessus. Ajoute un rectangle tout plat en dessous pour faire le plateau.',
                'Dessine des vagues sur chaque rectangle pour faire la crème.',
                'Dessine des petits ponts en bas du plus grand rectangle. Ajoute une vague au dessus. Puis dessine des petits ronds à chaque étage, pour faire les cerises confites.',
                'Dessine 3 rectangles debouts, tout en haut du gâteau, pour les bougies. Ajoute des traits pour les décorer.',
                'Fais un trait sur chaque bougie avec une flamme en forme de goutte d’eau. Ajoute 3 traits au-dessus des flammes pour montrer qu’elles sont allumées. C’est prêt !'
              ],
            },
            {
              code: 'clown',
              label: 'Le clown jongleur',
              description: 'Nez rouge, savates et grand pantalon, le clown est haut en couleurs ! Il jongle mais s’emmêle les pinceaux... Dessine-le avant qu’il ne dégringole.',
              difficulty: 2,
              video: 'https://www.youtube.com/embed/OeSpR3EGziw?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/clown.pdf',
              steps: [
                'Fais un rond pour la tête et un oeuf en dessous pour le corps. Ajoute 3 traits sous l’oeuf pour les jambes.',
                'Au bout des traits, dessine 2 ponts pour les chaussures et 2 traits pour les semelles.',
                'Pour les bras, dessine 2 rectangles. Au bout, fais 2 petits rectangles dans l’autre sens, pour les manches. Termine les mains en forme de fleur.',
                'Dessine 3 cercles pour le visage : un gros au milieu pour le nez rouge et deux petits au dessus pour les yeux avec des points dedans. Fais deux triangles au dessus des yeux pour le maquillage.',
                'Dessine une banane sous le nez pour maquiller le clown, avec une ligne au milieu pour le sourire.',
                'Ajoute des nuages sur le côté de la tête pour les cheveux.',
                'Dessine 3 ronds sur sa veste. Un gros en haut, un moyen au milieu et un petit en bas. Ajoute son chapeau en faisant un rectangle fin, un triangle et une petite boule au-dessus.',
                'Dessine 3 cercles au-dessus de la main gauche et 3 cercles au-dessus de la main droite pour le faire jongler. Ton clown est prêt à entrer en piste !',
              ],
            },
            {
              code: 'elephant',
              label: 'L’éléphant équilibriste',
              description: 'Les pas de l’éléphant résonnent et font trembler le sol. Dessine-le sur un plot, la patte levée bien haut !',
              difficulty: 1,
              video: 'https://www.youtube.com/embed/5s2TtJ1d1rI?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/elephant.pdf',
              steps: [
                'Trace un grand “C” à l’envers pour faire l’oreille. Puis un petit pont pour le dessus de la tête.',
                'Poursuis le petit pont en dessinant un “U” pour faire la trompe. Le bout de la trompe est comme un “M”. Dessine un autre “U” pour faire le dessous de la trompe.',
                'Ajoute un petit “V” pour lier la trompe à l’oreille et un point pour faire l’oeil.',
                'Pars du bas de l’oreille et dessine une forme de pot de fleur, arrondie sur le dessus pour le corps.',
                'Ajoute un trait pour faire les pattes au milieu du corps surmonté d’un “U” pour dessiner le bas du ventre.',
                'Fais une patte levée en ajoutant un rectangle sous l’oreille. Fais 3 petits ponts en bas de chaque patte pour dessiner les ongles et 3 traits sur la trompe pour les rides. Fais un triangle long et fin pour la queue.',
                'Ajoute un pot de fleur à l’envers sous l’éléphant. Décore-le avec des triangles en bas, des lignes en haut et des pois au milieu.',
                'Pour finir, ajoute un petit chapeau en faisant un trait pour le bas et un arrondi pour le dessus. Bravo ! Ton éléphant tient en équilibre.',
              ],
            },
            {
              code: 'magician',
              label: 'Le magicien époustouflant',
              description: 'Abracadabra, le magicien fait des tours et le public est bouche-bée. Dessine-le avec un chapeau magique, duquel sort un cadeau fantastique.',
              difficulty: 3,
              video: 'https://www.youtube.com/embed/Bj55HHe18hg?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/magicien.pdf',
              steps: [
                'Dessine un cercle pour la tête. Ajoute un “C” collé au milieu à gauche pour l’oreille, fais un “C” à l’envers au même niveau à droite pour l’autre oreille.',
                'Dessine un oeuf pour le corps avec 3 traits en dessous pour les jambes.',
                'Ajoute 2 ponts à l’envers sur le haut du visage pour les cheveux. Dessine une ligne pour le sourire et un petit “c” pour faire le nez. Dessine 2 cercles pour les yeux avec un point dedans.',
                'Au bout des jambes, dessine 2 ponts pour le dessus des chaussures avec 2 traits en dessous pour les semelles.',
                'Ajoute 2 rectangles sur le côté de l’oeuf pour les bras. Au bout des bras, fais 2 petits rectangles dans l’autre sens pour les manches.',
                'Ajoute une main à gauche en forme de demi-cercle fermé par un trait droit. Fais la main droite en forme de fleur.',
                'Pour habiller le magicien, dessine un grand triangle derrière lui pour la cape. Ajoute 2 petits triangles pointant vers le bas sous sa tête pour le col de la chemise et 2 traits sur le ventre pour les poches. Et termine par un “Y” à l’envers sur le ventre pour faire le gilet.',
                'Ajoute un carré posé sur la main gauche pour le chapeau, avec un rectangle un peu plus large au-dessus pour la visière. Dessine une ligne sur le carré pour faire le ruban.',
                'Dessine un carré au dessus du chapeau avec 2 croix dedans pour faire le ruban. Sur le cadeau ajoute un cercle, avec 2 oeufs sur les côtés pour le noeud. Ton magicien vient d’apparaître !',
              ],
            },
            {
              code: 'sealion',
              label: 'L’otarie chapeautée',
              description: 'L’otarie fait le pitre sous le chapiteau.Elle glisse, danse et baille sans couvrir sa grande bouche.Dessine-la avec son chapeau de fête.',
              difficulty: 2,
              video: 'https://www.youtube.com/embed/2S1fYLHABy4?rel=0&showinfo=0',
              tutorial: 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/circus/otarie.pdf',
              steps: [
                'Dessine la moitié d’un cercle pour le dessus de la tête et une petite boule au bout à droite pour le nez.',
                'Ajoute le sourire sous la petite boule, en forme de “U”. Dessine le tour de la bouche. Fais un cercle pour l’oeil avec un point dedans.',
                'Dessine un petit creux pour le cou puis une grande ligne en forme de sourire pour faire le ventre jusqu’à la queue.',
                'Dessine le bout de la queue un peu comme un “M”.',
                'Trace une ligne pour le dos, qui remonte jusqu’à la tête. Termine avec 2 nageoires en triangles.',
                'Ajoute un triangle sur la tête surmonté d’une boule. C’est son chapeau. Ton otarie est vraiment chapeautée !'
              ],
            },
          ];
        }
      },
      onEnter: function () {
        angular.element('body').addClass('state-drawings');
      },
      onExit: function () {
        angular.element('body').removeClass('state-drawings');
      },
    })

    .state('app.circusBirthday.drawings.drawing', {
      url: '/drawing/:code',
      templateUrl: 'views/circus-birthday/drawing.html',
      controller: 'CircusBirthdayDrawing',
      controllerAs: 'ctrl',
      resolve: {
        drawing: ['_', 'drawings', '$stateParams', function (_, drawings, $stateParams) {
          return _.find(drawings, {code: $stateParams.code });
        }],
        nextDrawing: ['_', 'drawings', 'drawing', function (_, drawings, drawing) {
          var idx = _.indexOf(drawings, drawing);

          return drawings[idx + 1] ? drawings[idx + 1] : drawings[0];
        }],
      },
      onEnter: ['drawing', function (drawing) {
        angular.element('body').addClass('state-drawing').addClass('drawing-'+drawing.code);
        angular.element('#app-circus-birthday').scrollTop(0);
      }],
      onExit: ['drawing', function (drawing) {
        angular.element('body').removeClass('state-drawing').removeClass('drawing-'+drawing.code);
      }],
    })
  ;

}

})(angular);
