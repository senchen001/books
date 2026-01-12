<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUploadedOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('uploaded_orders', function (Blueprint $table) {

            $table->unsignedBigInteger('userid')->nullable();  // userid в начале

            $table->bigIncrements('id');  // id с автоинкрементом и первичным ключом

            $table->unsignedBigInteger('originid')->nullable(); // originid для назначения позже

            $table->string('seqNum')->nullable();       // Код ФП
            $table->string('caption')->nullable();      // Наименование
            $table->string('author')->nullable();       // Автор
            $table->string('year')->nullable();         // Год
            $table->string('ART')->nullable();          // Артикул
            $table->integer('quantity')->nullable();    // Кол-во
            $table->decimal('price', 10, 2)->nullable(); // Цена, руб.

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('uploaded_orders');
    }
}
