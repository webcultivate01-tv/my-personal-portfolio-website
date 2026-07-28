<?php
namespace App\Core;

/**
 * Renders a PHP view file, optionally wrapped in a layout.
 */
class View
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = dirname(__DIR__) . '/Views/';
    }

    /**
     * @param string      $view    e.g. 'dashboard/index'
     * @param array       $data    variables made available to the view
     * @param string|null $layout  layout name in Views/layouts, or null for none
     */
    public function render(string $view, array $data = [], ?string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);

        // Capture the view's HTML into $content for the layout.
        ob_start();
        require $this->viewsPath . $view . '.php';
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        require $this->viewsPath . 'layouts/' . $layout . '.php';
    }
}
