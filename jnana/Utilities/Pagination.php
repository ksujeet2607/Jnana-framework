<?php

namespace Jnana\Utilities;

class Pagination
{
    public static function Generate(int $page = 1, int $total_pages = 10, string $href='/', int $visiblePages = 5): string
    {
        // Generate refined pagination
        $paginationHTML = '<nav>
            <ul class="pagination">';

        $paginationHTML .= '<li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                            <a class="page-link" href="'.$href.'?page=1" data-page="1">First</a>
                        </li>';
        $paginationHTML .= '<li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                            <a class="page-link" href="'.$href.'?page=' . ($page - 1) . '" data-page="' . ($page - 1) . '">Previous</a>
                        </li>';

        // Define pagination range
        $startPage = max(1, $page - floor($visiblePages / 2));
        $endPage = min($total_pages, $startPage + $visiblePages - 1);

        if ($startPage > 1) {
            $paginationHTML .= '<li class="page-item"><span class="page-link">...</span></li>';
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $paginationHTML .= '<li class="page-item ' . (($page == $i) ? 'active' : '') . '">
                                <a class="page-link" href="'.$href.'?page=' . $i . '" data-page="' . $i . '">' . $i . '</a>
                            </li>';
        }

        if ($endPage < $total_pages) {
            $paginationHTML .= '<li class="page-item"><span class="page-link">...</span></li>';
        }

        $paginationHTML .= '<li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                            <a class="page-link" href="'.$href.'?page=' . ($page + 1) . '" data-page="' . ($page + 1) . '">Next</a>
                        </li>';
        $paginationHTML .= '<li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                            <a class="page-link" href="'.$href.'?page=' . $total_pages . '" data-page="' . $total_pages . '">Last</a>
                        </li>';

        $paginationHTML .= '</ul>
            </nav>';

        return $paginationHTML;
    }
    public static function GenerateForAjax(int $page = 1, int $total_pages = 10, int $visiblePages = 5): string
    {
        // Generate refined pagination
        $paginationHTML = '<nav>
            <ul class="pagination">';

        $paginationHTML .= '<li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                            <a class="page-link" href="#" data-page="1">First</a>
                        </li>';
        $paginationHTML .= '<li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                            <a class="page-link" href="#" data-page="' . ($page - 1) . '">Previous</a>
                        </li>';

        // Define pagination range
        $startPage = max(1, $page - floor($visiblePages / 2));
        $endPage = min($total_pages, $startPage + $visiblePages - 1);

        if ($startPage > 1) {
            $paginationHTML .= '<li class="page-item"><span class="page-link">...</span></li>';
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $paginationHTML .= '<li class="page-item ' . (($page == $i) ? 'active' : '') . '">
                                <a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a>
                            </li>';
        }

        if ($endPage < $total_pages) {
            $paginationHTML .= '<li class="page-item"><span class="page-link">...</span></li>';
        }

        $paginationHTML .= '<li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                            <a class="page-link" href="#" data-page="' . ($page + 1) . '">Next</a>
                        </li>';
        $paginationHTML .= '<li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                            <a class="page-link" href="#" data-page="' . $total_pages . '">Last</a>
                        </li>';

        $paginationHTML .= '</ul>
            </nav>';

        return $paginationHTML;
    }

}