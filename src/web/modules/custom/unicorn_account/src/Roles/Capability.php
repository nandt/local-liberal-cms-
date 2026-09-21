<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Roles;

use Drupal\unicorn_account\Permissions\Permissions;

enum Capability: string {

  case UserProfile = 'user_profile';
  case Dashboard = 'dashboard';
  case PublishDashboard = 'publish_dashboard';
  case Articles = 'articles';
  case Sections = 'sections';
  case Categories = 'categories';
  case Authors = 'authors';
  case SpecialFeatures = 'special_features';
  case AdvertisingTools = 'advertising_tools';
  case Settings = 'settings';
  case Footer = 'footer';
  case Sources = 'sources';
  case Series = 'series';
  case PreviewLink = 'preview_link';
  case UnpublishedPreview = 'unpublished_preview';
  case LiberalMarketsDashboard = 'liberal_markets_dashboard';
  case SeoMetadata = 'seo_metadata';

  /**
   * Τα actions κάθε capability → τα Drupal permissions που το ξεκλειδώνουν.
   *
   * actions: view (GET), create (POST), update (PATCH), delete (DELETE),
   * access (non-CRUD tools/sections). Μόνο όσα actions ισχύουν για κάθε capability.
   *
   * @return array<string, list<string>>
   */
  public function actions(): array {
    return match ($this) {
      self::UserProfile => [
        'view' => [Permissions::ManageOwnProfile->value],
        'update' => [Permissions::ManageOwnProfile->value],
      ],
      /*
       * Το dashboard δεν είναι απλό tab: ο editor στήνει από εκεί το home
       * page layout, οπότε η ανάλυση το θέλει CRUD full σε Admin και Editor.
       * Το access έμενε μόνο του και ο front-end δεν είχε flag για να δείξει
       * το editing, παρότι το POST στο /customapi/home-layout ήδη περνούσε.
       * Το AccessDashboard μπαίνει σε κάθε action ώστε η capability να μένει
       * κλειδωμένη πίσω από το dashboard, ό,τι κι αν έχει κανείς αλλού.
       */
      self::Dashboard => [
        'access' => [Permissions::AccessDashboard->value],
        'view' => [Permissions::AccessDashboard->value],
        'create' => [Permissions::AccessDashboard->value, 'administer site configuration'],
        'update' => [Permissions::AccessDashboard->value, 'administer site configuration'],
        'delete' => [Permissions::AccessDashboard->value, 'administer site configuration'],
      ],
      self::PublishDashboard => ['access' => [Permissions::AccessDashboard->value, 'administer site configuration']],
      self::Articles => [
        'view' => [Permissions::ViewAnyArticle->value],
        'create' => ['create article_liberal content', 'use text format basic_html', 'use text format full_html', 'access files overview'],
        'update' => ['edit any article_liberal content', 'use text format basic_html', 'use text format full_html', 'access files overview'],
        'delete' => ['delete any article_liberal content'],
      ],
      self::Sections => [
        'view' => [Permissions::ViewSection->value],
        'create' => [Permissions::CreateSection->value],
        'update' => [Permissions::UpdateSection->value],
        'delete' => [Permissions::DeleteSection->value],
      ],
      self::Categories => [
        'view' => ['edit terms in category'],
        'create' => ['create terms in category'],
        'update' => ['edit terms in category'],
        'delete' => ['delete terms in category'],
      ],
      self::Authors => [
        'view' => ['edit terms in arthrografos'],
        'create' => ['create terms in arthrografos'],
        'update' => ['edit terms in arthrografos'],
        'delete' => ['delete terms in arthrografos'],
      ],
      self::SpecialFeatures => [
        'view' => [Permissions::ViewOblations->value],
        'create' => ['create terms in oblations'],
        'update' => ['edit terms in oblations'],
        'delete' => ['delete terms in oblations'],
      ],
      /*
       * Τρία features κάτω από την ίδια ομπρέλα: το lazy load των adtools, το
       * Read More ad tool και το News Feed ad banner. Τα δύο τελευταία ήρθαν με
       * δικά τους permissions και δεν είχαν προστεθεί εδώ, οπότε ο Editor και ο
       * Commercial έπαιρναν μόνο το lazy load και έμεναν κλειδωμένοι έξω από τα
       * endpoints — ενώ η ανάλυση τους δίνει view και edit.
       */
      self::AdvertisingTools => [
        'access' => [
          'change lazy load adtools settings',
          'view advertising tools page',
          'view promo banner',
        ],
        'view' => [
          'view advertising tools page',
          'view promo banner',
        ],
        'update' => [
          'change lazy load adtools settings',
          'administer advertising tools page',
          'administer promo banner',
        ],
      ],
      self::Settings => [
        'access' => [
          'administer site configuration',
          'change refresh on viewable settings',
        ],
        'view' => [
          'administer site configuration',
          'change refresh on viewable settings',
        ],
        'update' => [
          'administer site configuration',
          'change refresh on viewable settings',
        ],
      ],
      self::Footer => [
        'access' => ['edit any page content'],
        'update' => ['edit any page content'],
      ],
      self::Sources => [
        'view' => ['edit terms in piges_arthron'],
        'create' => ['create terms in piges_arthron'],
        'update' => ['edit terms in piges_arthron'],
        'delete' => ['delete terms in piges_arthron'],
      ],
      self::Series => [
        'view' => ['edit terms in series'],
        'create' => ['create terms in series'],
        'update' => ['edit terms in series'],
        'delete' => ['delete terms in series'],
      ],
      self::PreviewLink => ['access' => [Permissions::GenerateArticlePreviewLink->value]],
      self::UnpublishedPreview => ['view' => ['view articles flagged for client preview']],
      /*
       * Ίδια ιστορία με το dashboard: το markets είναι ξεχωριστή περιοχή με
       * δικό του layout, και το ίδιο permission φυλάει και το διάβασμα και το
       * γράψιμο στο /customapi/home-layout?variant=markets. Τα actions
       * εκτίθενται ώστε ο front-end να μη μαντεύει από τον ρόλο.
       */
      self::LiberalMarketsDashboard => [
        'access' => [Permissions::AccessLiberalMarketsDashboard->value],
        'view' => [Permissions::AccessLiberalMarketsDashboard->value],
        'create' => [Permissions::AccessLiberalMarketsDashboard->value],
        'update' => [Permissions::AccessLiberalMarketsDashboard->value],
        'delete' => [Permissions::AccessLiberalMarketsDashboard->value],
      ],
      self::SeoMetadata => ['access' => ['administer meta tags']],
    };
  }

  /**
   * Επίπεδη λίστα των permissions (για το provisioning των roles).
   *
   * @param list<string>|null $actions
   *   Αν δοθεί, περιορίζει το αποτέλεσμα μόνο σε αυτά τα actions.
   *
   * @return list<string>
   */
  public function permissions(?array $actions = NULL): array {
    $permissions = [];
    foreach ($this->actions() as $action => $action_permissions) {
      if ($actions !== NULL && !in_array($action, $actions, TRUE)) {
        continue;
      }
      foreach ($action_permissions as $permission) {
        $permissions[] = $permission;
      }
    }

    return array_values(array_unique($permissions));
  }

}
