<?php
namespace Tests\Unit\App\Database\Fixtures;

use App\Abstracts\AbstractModel;

/**
 * AuthorModel - Modello di test per autori
 *
 * Tabella: test_authors
 * Relazione: hasMany con BookModel (un autore ha molti libri)
 *
 * @property int|null $author_id
 * @property string|null $name
 * @property string|null $country
 * @property int|null $birth_year
 * @property int|null $books_count
 * @property BookModel $books
 */
class AuthorModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_authors')
            ->id('author_id')
            ->hasMany('books', BookModel::class, 'author_id')
            ->withCount('books_count', BookModel::class, 'author_id')
            ->string('name', 100)->label('Nome Autore')->required()
            ->string('country', 50)->label('Paese')
            ->int('birth_year')->label('Anno di nascita');
    }
}
