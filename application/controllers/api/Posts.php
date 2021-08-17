<?php
require 'application/libraries/REST_Controller.php';

class Posts extends REST_Controller
{

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->post_load_more_count = 6;
        $this->comment_limit = 5;
    }

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function index_get($id = 0)
    {
        $data = null;

        if (!empty($id)) {
            $post = $this->post_model->get_post($id);
            if (!empty($post)) {
                $data = $this->post_get($post);
                $this->response(['data' => $data], REST_Controller::HTTP_OK);
            } else {
                //not found
                $this->response(['errors' => 'Post not found'], REST_Controller::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $pagination = $this->paginate(generate_url('api-posts'), get_total_post_count());
            $data = get_cached_data('posts_page_' . $pagination['current_page']);
            if (empty($data)) {
                $data = $this->post_model->get_paginated_posts($pagination['offset'], $pagination['per_page']);
                set_cache_data('posts_page_' . $pagination['current_page'], $data);
            }
            $this->response(['data' => $data, 'pagination' => $pagination], REST_Controller::HTTP_OK);
        }

//
    }

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function post_by_category_get($slug = 0)
    {
        $data = null;

        if (!empty($slug)) {
            $category = $this->category_model->get_category_by_slug($slug);
            if (!empty($category)) {
                if (function_exists('get_site_mod')) {
                    get_site_mod();
                }
                $data = $this->category($category);
                $this->response(['data' => $data], REST_Controller::HTTP_OK);
            } else {
                $this->response(['errors' => 'Category not found'], REST_Controller::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $this->response(['errors' => 'Category not specified'], REST_Controller::HTTP_UNPROCESSABLE_ENTITY);

        }

//
    }

    public function subcategory_get($parent_slug, $slug)
    {

        $slug = clean_slug($slug);
        $category = $this->category_model->get_category_by_slug($slug);
        if (empty($category)) {
            $this->response(['errors' => 'Category not found'], REST_Controller::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $data['title'] = $category->name;
            $data['description'] = $category->description;
            $data['keywords'] = $category->keywords;
            $data['category'] = $category;
            $data['parent_category'] = null;
            if (!empty($category->parent_id)) {
                $data['parent_category'] = get_category($category->parent_id, $this->categories);
            }

            $count_key = 'posts_count_category' . $category->id;
            $posts_key = 'posts_category' . $category->id;
            //category posts count
            $total_rows = get_cached_data($count_key);
            if (empty($total_rows)) {
                $total_rows = $this->post_model->get_post_count_by_category($category->id);
                set_cache_data($count_key, $total_rows);
            }
            //set paginated
            $pagination = $this->paginate(generate_category_url($category), $total_rows);
            $data['posts'] = get_cached_data($posts_key . '_page' . $pagination['current_page']);
            if (empty($data['posts'])) {
                $data['posts'] = $this->post_model->get_paginated_category_posts($category->id, 1, 1);
                set_cache_data($posts_key . '_page' . $pagination['current_page'], $data['posts']);
            }
            $data['pagination'] = $pagination;


            $this->response(['data' => $data], REST_Controller::HTTP_OK);

        }
    }

    public function categories_get()
    {
        $data = $this->category_model->get_parent_categories();
        $this->response(['data' => $data], REST_Controller::HTTP_OK);
    }

    public function subcategories_get($id=0)
    {
        if (empty($id)) {
            $data = $this->category_model->get_subcategories();
            $this->response(['data' => $data], REST_Controller::HTTP_OK);
        } else {
            $data = $this->category_model->get_subcategories_by_parent_id($id);
            $this->response(['data' => $data], REST_Controller::HTTP_OK);
        }
    }
    public function popular_posts_get()
    {
            $data = $this->post_model->get_popular_posts_all_time($this->selected_lang->id);
            $this->response(['data' => $data], REST_Controller::HTTP_OK);
    }
    public function breaking_news_get()
    {
            $data = $this->post_model->get_breaking_news();
            $this->response(['data' => $data], REST_Controller::HTTP_OK);
    }
    public function posts_by_tags_get($tag_slug)
    {
        $tag_slug = clean_slug($tag_slug);
        $data['tag'] = $this->tag_model->get_tag($tag_slug);
        //check tag exists
        if (empty($data['tag'])) {
            redirect(lang_base_url());
        }
        $data['title'] = $data['tag']->tag;
        $data['description'] = trans("tag") . ': ' . $data['tag']->tag;
        $data['keywords'] = trans("tag") . ', ' . $data['tag']->tag;
        //set paginated
        $pagination = $this->paginate(generate_tag_url($tag_slug), $this->post_model->get_post_count_by_tag($tag_slug));
        $data['posts'] = $this->post_model->get_paginated_tag_posts($tag_slug, $pagination['offset'], $pagination['per_page']);

        $this->response(['data' => $data, 'pagination' => $pagination], REST_Controller::HTTP_OK);
    }

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function index_post()
    {
        $input = $this->input->post();
        $this->db->insert('items', $input);

        $this->response(['Item created successfully.'], REST_Controller::HTTP_OK);
    }

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function index_put($id)
    {
        $input = $this->put();
        $this->db->update('items', $input, array('id' => $id));

        $this->response(['Item updated successfully.'], REST_Controller::HTTP_OK);
    }

    /**
     * Get All Data from this method.
     *
     * @return Response
     */
    public function index_delete($id)
    {
        $this->db->delete('items', array('id' => $id));

        $this->response(['Item deleted successfully.'], REST_Controller::HTTP_OK);
    }

    public function post_get($post)
    {


        $data['post'] = $post;
        $data['post_user'] = $this->auth_model->get_user($post->user_id);
        $data['post_tags'] = $this->tag_model->get_post_tags($post->id);
        $data['post_images'] = $this->post_file_model->get_post_additional_images($post->id);

        $data['comments'] = $this->comment_model->get_comments($post->id, $this->comment_limit);
        $data['comment_limit'] = $this->comment_limit;
        $data['related_posts'] = $this->post_model->get_related_posts($post->category_id, $post->id);
        $data['previous_post'] = $this->post_model->get_previous_post($post->id);
        $data['next_post'] = $this->post_model->get_next_post($post->id);

        $data['is_reading_list'] = $this->reading_list_model->is_post_in_reading_list($post->id);

        $data['post_type'] = $post->post_type;

        if (!empty($post->feed_id)) {
            $data['feed'] = $this->rss_model->get_feed($post->feed_id);
        }

        $data = $this->set_post_meta_tags($post, $data['post_tags'], $data);

        $this->reaction_model->set_voted_reactions_session($post->id);
        $data["reactions"] = $this->reaction_model->get_reaction($post->id);

        //gallery post
        if ($post->post_type == "gallery") {
            $data['gallery_post_total_item_count'] = $this->post_item_model->get_post_list_items_count($post->id, $post->post_type);
            $data['gallery_post_item'] = $this->post_item_model->get_gallery_post_item_by_order($post->id, 1);
            $data['gallery_post_item_order'] = 1;
        }
        //sorted list post
        if ($post->post_type == "sorted_list") {
            $data['sorted_list_items'] = $this->post_item_model->get_post_list_items($post->id, $post->post_type);
        }

        //quiz
        if ($post->post_type == "trivia_quiz" || $post->post_type == "personality_quiz") {
            $data['quiz_questions'] = $this->quiz_model->get_quiz_questions($post->id);
        }

        return $data;
    }

    private function set_post_meta_tags($post, $post_tags, $data)
    {
        $data['title'] = $post->title;
        $data['description'] = $post->summary;
        $data['keywords'] = $post->keywords;

        $data['og_title'] = $post->title;
        $data['og_description'] = $post->summary;
        $data['og_type'] = "article";
        $data['og_url'] = generate_post_url($post);
        $data['og_image'] = base_url() . $post->image_big;
        if (!empty($post->image_url)) {
            $data['og_image'] = $post->image_url;
        }
        $data['og_width'] = "750";
        $data['og_height'] = "422";
        $data['og_creator'] = $post->author_username;
        $data['og_author'] = $post->author_username;
        $data['og_published_time'] = $post->created_at;
        $data['og_modified_time'] = $post->updated_at;
        if (empty($post->updated_at)) {
            $data['og_modified_time'] = $post->created_at;
        }
        $data['og_tags'] = $post_tags;
        return $data;
    }

    private function category($category)
    {

        $data['title'] = $category->name;
        $data['description'] = $category->description;
        $data['keywords'] = $category->keywords;
        $data['category'] = $category;

        $count_key = 'posts_count_category' . $category->id;
        $posts_key = 'posts_category' . $category->id;
        //category posts count
        $total_rows = get_cached_data($count_key);
        if (empty($total_rows)) {
            $total_rows = $this->post_model->get_post_count_by_category($category->id);
            set_cache_data($count_key, $total_rows);
        }
        //set paginated
        $pagination = $this->paginate(generate_category_url($category), $total_rows);
        $data['posts'] = get_cached_data($posts_key . '_page' . $pagination['current_page']);
        if (empty($data['posts'])) {
            $data['posts'] = $this->post_model->get_paginated_category_posts($category->id, $pagination['offset'], $pagination['per_page']);
            set_cache_data($posts_key . '_page' . $pagination['current_page'], $data['posts']);
        }
        $data['pagination'] = $pagination;
        return $data;
    }
}
