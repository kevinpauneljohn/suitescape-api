<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\RoomCategory;
use App\Models\Video;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VideoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $videos = [
            "2022-11-20_@lacabanaprivatecabin_7168033003455712539.mp4",
            "2023-05-02_@kalawakanglasscabins_7228489007469055237.mp4",
            "2023-05-04_@yasumistaycationmanila_7229132248874847494.mp4",
            "2023-05-08_@rica.mabelle_7230814748785347867.mp4",
            "2023-05-19_@aandmbasics_7234831250383523078.mp4",
            "2023-06-10_@property_metrobacolod_7242829768629030149.mp4",
            "2023-06-16_@beastproperties_7245146781921611013.mp4",
            "2023-06-28_@thedailywanderer__7249728597475314949.mp4",
            "2023-07-05_@cgchillcation_7252221153463356678.mp4",
            "2023-08-21_@winter.hotel.mani_7269618664079510789.mp4",
            "2023-08-30_@johnkevinpaunel_7273131105790840065.mp4",
            "2023-09-04_@johnkevinpaunel_7274990508290657537.mp4",
            "2023-09-09_@johnkevinpaunel_7276850943063362818.mp4",
            "2023-09-09_@realstaycation_7276808719139097857.mp4",
            "2023-09-11_@recaresortofficial_7277516338825022725.mp4"
        ];

        foreach ($videos as $video) {
            $listing = Listing::factory()->create();

            // Temporary solution for now so that listing has a price
            $listing->roomCategories()->save(
                RoomCategory::factory()->make()
            );

            $listing->videos()->save(
                Video::factory()->make([
                    'filename' => $video,
                ])
            );
        }
    }
}
