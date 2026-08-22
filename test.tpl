===== {{ store_name }} =====
{% for section in sections %}
{{ section.name }}
{{ separator }}
{% for category in section.categories %}  [{{ category.name }}]
{% for product in category.products %}    - {{ product.name }} (${{ product.price }}) x{{ product.stock }}
{% endfor %}
{% endfor %}{% endfor %}
Powered by Template Engine v{{ version }}
