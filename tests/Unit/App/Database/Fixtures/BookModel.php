<?php
namespace Tests\Unit\App\Database\Fixtures;

use App\Abstracts\AbstractModel;

/**
 * BookModel - Modello di test per libri
 *
 * Tabella: test_books
 * Relazione: belongsTo con AuthorModel (un libro appartiene a un autore)
 *
 * @property int|null $book_id
 * @property int|null $author_id
 * @property string|null $title
 * @property int|null $year
 * @property float|null $price
 * @property AuthorModel $author
 */
class BookModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_books')
            ->id('book_id')
            ->int('author_id')->label('ID Autore')->required()
                ->belongsTo('author', AuthorModel::class, 'author_id')
            ->string('title', 200)->label('Titolo')->required()
            ->int('year')->label('Anno Pubblicazione')
            ->decimal('price', 10, 2)->label('Prezzo');
    }
}
